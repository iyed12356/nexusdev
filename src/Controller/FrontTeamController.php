<?php

namespace App\Controller;

use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/FTeam')]
final class FrontTeamController extends AbstractController
{
    #[Route(name: 'front_team_index', methods: ['GET'])]
    public function index(TeamRepository $teamRepository): Response
    {
        return $this->render('front/team/index.html.twig', [
            'teams' => $teamRepository->findAll(),
        ]);
    }
}
