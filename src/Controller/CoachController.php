<?php

namespace App\Controller;

use App\Entity\Coach;
use App\Entity\CoachingSession;
use App\Entity\Player;
use App\Form\CoachType;
use App\Repository\CoachRepository;
use App\Repository\CoachingSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
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
        CoachingSessionRepository $coachingSessionRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
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
            'mode' => 'management',
        ]);
        }

        $user = $this->getUser();
        if (!$user || !$user->getCoach()) {
            throw $this->createAccessDeniedException('You do not have a coach profile.');
        }

        $coach = $user->getCoach();

        $dateParam = (string) $request->query->get('date', '');
        $selectedDate = null;
        if ($dateParam !== '') {
            try {
                $selectedDate = new \DateTimeImmutable($dateParam);
            } catch (\Throwable) {
                $selectedDate = null;
            }
        }
        if (!$selectedDate) {
            $selectedDate = new \DateTimeImmutable('today');
        }

        $dayStart = $selectedDate->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $sessions = $coachingSessionRepository->findForCoachBetween($coach, $dayStart, $dayEnd);

        $session = new CoachingSession();
        $session->setCoach($coach);
        $session->setStatus('CONFIRMED');

        $createForm = $this->createFormBuilder($session)
            ->add('player', EntityType::class, [
                'class' => Player::class,
                'choice_label' => 'nickname',
                'placeholder' => 'Select a player',
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Meeting date & time',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->getForm();

        $createForm->handleRequest($request);
        if ($createForm->isSubmitted() && $createForm->isValid()) {
            $entityManager->persist($session);
            $entityManager->flush();

            $this->addFlash('success', 'Meeting created.');
            return $this->redirectToRoute('app_coach_back', ['date' => $dayStart->format('Y-m-d')], Response::HTTP_SEE_OTHER);
        }

        return $this->render('coach/dashboard.html.twig', [
            'coach' => $coach,
            'sessions' => $sessions,
            'selectedDate' => $dayStart,
            'createSessionForm' => $createForm->createView(),
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
