<?php

namespace App\Controller;

use App\Repository\OrganizationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/FOrganization')]
final class FrontOrganizationController extends AbstractController
{
    #[Route(name: 'front_organization_index', methods: ['GET'])]
    public function index(OrganizationRepository $organizationRepository): Response
    {
        $organizations = $organizationRepository->findBy(['deletedAt' => null]);

        return $this->render('front/organization/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }
}
