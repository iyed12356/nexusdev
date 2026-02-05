<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Team;
use App\Form\OrganizationType;
use App\Form\TeamType;
use App\Repository\OrganizationRepository;
use App\Repository\PlayerRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/BOrganization')]
final class OrganizationController extends AbstractController
{
    #[Route(name: 'app_organization_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        OrganizationRepository $organizationRepository,
        PlayerRepository $playerRepository,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to manage an organization.');
        }

        // Admin view: list all organizations (for any user having ROLE_ADMIN)
        if ($this->isGranted('ROLE_ADMIN')) {
            $organizations = $organizationRepository->findAll();

            return $this->render('organization/back_admin.html.twig', [
                'organizations' => $organizations,
            ]);
        }

        // Get or create organization for this user
        $organization = $organizationRepository->findOneBy(['owner' => $user]);
        if (!$organization) {
            $organization = new Organization();
            $organization->setOwner($user);
        }

        // Organization form handling
        $orgForm = $this->createForm(OrganizationType::class, $organization);
        $orgForm->handleRequest($request);

        if ($orgForm->isSubmitted() && $orgForm->isValid()) {
            /** @var UploadedFile $logoFile */
            $logoFile = $orgForm->get('logo')->getData();
            
            if ($logoFile) {
                $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$logoFile->guessExtension();
                
                try {
                    $logoFile->move(
                        $this->getParameter('logos_directory'),
                        $newFilename
                    );
                    $organization->setLogo($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Error uploading logo file.');
                }
            }
            
            $isNew = $organization->getId() === null;
            if ($isNew) {
                $entityManager->persist($organization);
            }
            $entityManager->flush();

            $this->addFlash('success', $isNew ? 'Organization created successfully.' : 'Organization updated successfully.');

            return $this->redirectToRoute('app_organization_back', [], Response::HTTP_SEE_OTHER);
        }

        // Team creation form (only if organization exists)
        $team = new Team();
        $teamForm = null;
        $organizationTeams = [];
        
        if ($organization->getId()) {
            $teamForm = $this->createForm(TeamType::class, $team);
            $teamForm->handleRequest($request);

            if ($teamForm->isSubmitted() && $teamForm->isValid()) {
                /** @var UploadedFile $teamLogoFile */
                $teamLogoFile = $teamForm->get('logo')->getData();
                
                if ($teamLogoFile) {
                    $originalFilename = pathinfo($teamLogoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$teamLogoFile->guessExtension();
                    
                    try {
                        $teamLogoFile->move(
                            $this->getParameter('logos_directory'),
                            $newFilename
                        );
                        $team->setLogo($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Error uploading team logo file.');
                    }
                }
                
                $team->setOrganization($organization);
                $entityManager->persist($team);
                $entityManager->flush();

                $this->addFlash('success', 'Team created successfully.');

                return $this->redirectToRoute('app_organization_back', [], Response::HTTP_SEE_OTHER);
            }
            
            // Get organization's teams
            $organizationTeams = $teamRepository->findBy(['organization' => $organization]);
        }

        // Get all PRO players for scouting
        $players = $playerRepository->findProPlayers();

        return $this->render('organization/back.html.twig', [
            'organization' => $organization,
            'orgForm' => $orgForm,
            'teamForm' => $teamForm,
            'hasOrganization' => $organization->getId() !== null,
            'organizationTeams' => $organizationTeams,
            'players' => $players,
        ]);
    }

    #[Route('/recruit/{playerId}/{teamId}', name: 'app_organization_recruit', methods: ['POST'])]
    public function recruit(
        int $playerId,
        int $teamId,
        OrganizationRepository $organizationRepository,
        PlayerRepository $playerRepository,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $organization = $organizationRepository->findOneBy(['owner' => $user]);
        if (!$organization) {
            $this->addFlash('error', 'You must create an organization first.');
            return $this->redirectToRoute('app_organization_back');
        }

        $player = $playerRepository->find($playerId);
        $team = $teamRepository->find($teamId);

        if (!$player || !$team) {
            $this->addFlash('error', 'Player or team not found.');
            return $this->redirectToRoute('app_organization_back');
        }

        // Only PRO players can be recruited
        if (!$player->isPro()) {
            $this->addFlash('error', 'You can recruit only PRO players.');
            return $this->redirectToRoute('app_organization_back');
        }

        // Verify team belongs to this organization
        if ($team->getOrganization() !== $organization) {
            $this->addFlash('error', 'This team does not belong to your organization.');
            return $this->redirectToRoute('app_organization_back');
        }

        // Assign player to team
        $player->setTeam($team);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Player %s has been recruited to team %s!', $player->getNickname(), $team->getName()));

        return $this->redirectToRoute('app_organization_back');
    }

    #[Route('/{id}/delete', name: 'app_organization_delete', methods: ['POST'])]
    public function delete(Request $request, Organization $organization, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$organization->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($organization);
            $entityManager->flush();
            $this->addFlash('success', 'Organization deleted successfully.');
        }

        return $this->redirectToRoute('app_organization_back', [], Response::HTTP_SEE_OTHER);
    }
}
