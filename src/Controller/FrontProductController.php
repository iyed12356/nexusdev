<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/FProduct')]
final class FrontProductController extends AbstractController
{
    #[Route(name: 'front_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $productRepository->findBy(['deletedAt' => null]);

        return $this->render('front/product/index.html.twig', [
            'products' => $products,
        ]);
    }
}
