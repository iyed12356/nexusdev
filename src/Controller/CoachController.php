<?php

namespace App\Controller;

use App\Entity\Coach;
use App\Entity\CoachingSession;
use App\Entity\Player;
use App\Form\CoachType;
use App\Repository\CoachRepository;
use App\Repository\CoachingSessionRepository;
use App\Repository\PlayerRepository;
use App\Repository\StatisticRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
        PlayerRepository $playerRepository,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
        $qb = $coachRepository->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u');

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('u.username LIKE :search OR c.experienceLevel LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10
        );

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
            'pagination' => $pagination,
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

        $players = $playerRepository->findAll();

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

        // Get analytics data
        $totalSessions = $coachingSessionRepository->countTotalSessionsForCoach($coach);
        $completedSessions = $coachingSessionRepository->countCompletedSessionsForCoach($coach);
        $upcomingSessions = $coachingSessionRepository->countUpcomingSessionsForCoach($coach);
        $uniquePlayers = $coachingSessionRepository->findUniquePlayersForCoach($coach);
        
        // Calculate estimated earnings
        $estimatedEarnings = $completedSessions * (float) ($coach->getPricePerSession() ?? 0);

        return $this->render('coach/dashboard.html.twig', [
            'coach' => $coach,
            'sessions' => $sessions,
            'players' => $players,
            'selectedDate' => $dayStart,
            'createSessionForm' => $createForm->createView(),
            'analytics' => [
                'totalSessions' => $totalSessions,
                'completedSessions' => $completedSessions,
                'upcomingSessions' => $upcomingSessions,
                'uniquePlayersCount' => count($uniquePlayers),
                'estimatedEarnings' => $estimatedEarnings,
                'rating' => $coach->getRating(),
            ],
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

    #[Route('/profile', name: 'app_coach_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_COACH')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user || !$user->getCoach()) {
            throw $this->createAccessDeniedException('You do not have a coach profile.');
        }

        $coach = $user->getCoach();
        $form = $this->createFormBuilder($coach)
            ->add('experienceLevel', null, [
                'label' => 'Experience Level',
                'attr' => ['class' => 'form-control']
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Bio',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 5]
            ])
            ->add('pricePerSession', null, [
                'label' => 'Price per Session ($)',
                'attr' => ['class' => 'form-control']
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('app_coach_profile');
        }

        return $this->render('coach/profile.html.twig', [
            'coach' => $coach,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/sessions', name: 'app_coach_sessions', methods: ['GET'])]
    public function sessions(CoachingSessionRepository $coachingSessionRepository): Response
    {
        if (!$this->isGranted('ROLE_COACH')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user || !$user->getCoach()) {
            throw $this->createAccessDeniedException('You do not have a coach profile.');
        }

        $coach = $user->getCoach();
        $allSessions = $coachingSessionRepository->findAllSessionsForCoach($coach);

        return $this->render('coach/sessions.html.twig', [
            'coach' => $coach,
            'sessions' => $allSessions,
        ]);
    }

    #[Route('/session/{id}', name: 'app_coach_session_detail', methods: ['GET', 'POST'])]
    public function sessionDetail(Request $request, CoachingSession $session, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_COACH')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user || !$user->getCoach() || $session->getCoach() !== $user->getCoach()) {
            throw $this->createAccessDeniedException('You can only manage your own sessions.');
        }

        $form = $this->createFormBuilder($session)
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'PENDING',
                    'Confirmed' => 'CONFIRMED',
                    'Completed' => 'COMPLETED',
                    'Cancelled' => 'CANCELLED',
                ],
                'label' => 'Status',
                'attr' => ['class' => 'form-select']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Session Notes',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 5, 'placeholder' => 'Add notes about this session...']
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Session updated successfully.');
            return $this->redirectToRoute('app_coach_session_detail', ['id' => $session->getId()]);
        }

        return $this->render('coach/session_detail.html.twig', [
            'session' => $session,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/calendar', name: 'app_coach_calendar', methods: ['GET'])]
    public function calendar(Request $request, CoachingSessionRepository $coachingSessionRepository): Response
    {
        if (!$this->isGranted('ROLE_COACH')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user || !$user->getCoach()) {
            throw $this->createAccessDeniedException('You do not have a coach profile.');
        }

        $coach = $user->getCoach();

        $year = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('m'));

        // Validate month/year
        if ($month < 1 || $month > 12) {
            $month = date('m');
        }
        if ($year < 2020 || $year > 2030) {
            $year = date('Y');
        }

        $sessions = $coachingSessionRepository->findForCoachInMonth($coach, $year, $month);

        // Build calendar data
        $firstDay = new \DateTimeImmutable("$year-$month-01");
        $daysInMonth = (int) $firstDay->format('t');
        $startDayOfWeek = (int) $firstDay->format('w'); // 0 = Sunday

        return $this->render('coach/calendar.html.twig', [
            'coach' => $coach,
            'sessions' => $sessions,
            'year' => $year,
            'month' => $month,
            'monthName' => $firstDay->format('F'),
            'daysInMonth' => $daysInMonth,
            'startDayOfWeek' => $startDayOfWeek,
        ]);
    }

    #[Route('/player/{id}', name: 'app_coach_player_detail', methods: ['GET'])]
    public function playerDetail(Player $player, CoachingSessionRepository $coachingSessionRepository, StatisticRepository $statisticRepository): Response
    {
        if (!$this->isGranted('ROLE_COACH')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user || !$user->getCoach()) {
            throw $this->createAccessDeniedException('You do not have a coach profile.');
        }

        $coach = $user->getCoach();

        // Get coaching history with this player
        $sessions = $coachingSessionRepository->findSessionsBetweenCoachAndPlayer($coach, $player);

        // Get player statistics
        $statistics = $statisticRepository->findBy(['player' => $player]);

        return $this->render('coach/player_detail.html.twig', [
            'coach' => $coach,
            'player' => $player,
            'sessions' => $sessions,
            'statistics' => $statistics,
        ]);
    }
}
