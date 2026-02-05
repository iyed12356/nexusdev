<?php

namespace App\Controller;

use App\Entity\Team;
use App\Form\TeamType;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/BTeam')]
final class TeamController extends AbstractController
{
    #[Route(name: 'app_team_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        TeamRepository $teamRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $teams = $teamRepository->findAll();

        $teamId = $request->query->getInt('id', 0);
        if ($teamId > 0) {
            $team = $teamRepository->find($teamId);
            if (!$team) {
                throw $this->createNotFoundException('Team not found');
            }
        } else {
            $team = new Team();
        }

        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $team->getId() === null;
            if ($isNew) {
                $entityManager->persist($team);
            }
            $entityManager->flush();

            $this->addFlash('success', $isNew ? 'Team created successfully.' : 'Team updated successfully.');

            return $this->redirectToRoute('app_team_back', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('team/back.html.twig', [
            'teams' => $teams,
            'form' => $form,
            'editing' => $team->getId() !== null,
            'currentTeam' => $team,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_team_delete', methods: ['POST'])]
    public function delete(Request $request, Team $team, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$team->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($team);
            $entityManager->flush();
            $this->addFlash('success', 'Team deleted successfully.');
        }

        return $this->redirectToRoute('app_team_back', [], Response::HTTP_SEE_OTHER);
    }
}
