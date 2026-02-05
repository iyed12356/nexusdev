<?php

namespace App\Controller;

use App\Repository\CoachRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/FCoach')]
final class FrontCoachController extends AbstractController
{
    #[Route(name: 'front_coach_index', methods: ['GET'])]
    public function index(CoachRepository $coachRepository): Response
    {
        return $this->render('front/coach/index.html.twig', [
            'coaches' => $coachRepository->findAll(),
        ]);
    }
}
