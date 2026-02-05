<?php

namespace App\Controller;

use App\Repository\PlayerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/FPlayer')]
final class FrontPlayerController extends AbstractController
{
    #[Route(name: 'front_player_index', methods: ['GET'])]
    public function index(PlayerRepository $playerRepository): Response
    {
        return $this->render('front/player/index.html.twig', [
            'players' => $playerRepository->findAll(),
        ]);
    }
}
