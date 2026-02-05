<?php

namespace App\Controller;

use App\Entity\Coach;
use App\Form\CoachType;
use App\Repository\CoachRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/BCoach')]
final class CoachController extends AbstractController
{
    #[Route(name: 'app_coach_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        CoachRepository $coachRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $coaches = $coachRepository->findAll();

        $coachId = $request->query->getInt('id', 0);
        if ($coachId > 0) {
            $coach = $coachRepository->find($coachId);
            if (!$coach) {
                throw $this->createNotFoundException('Coach not found');
            }
        } else {
            $coach = new Coach();
        }

        $form = $this->createForm(CoachType::class, $coach);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $coach->getId() === null;
            if ($isNew) {
                $entityManager->persist($coach);
            }
            $entityManager->flush();

            $this->addFlash('success', $isNew ? 'Coach created successfully.' : 'Coach updated successfully.');

            return $this->redirectToRoute('app_coach_back', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('coach/back.html.twig', [
            'coaches' => $coaches,
            'form' => $form,
            'editing' => $coach->getId() !== null,
            'currentCoach' => $coach,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_coach_delete', methods: ['POST'])]
    public function delete(Request $request, Coach $coach, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$coach->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($coach);
            $entityManager->flush();
            $this->addFlash('success', 'Coach deleted successfully.');
        }

        return $this->redirectToRoute('app_coach_back', [], Response::HTTP_SEE_OTHER);
    }
}
