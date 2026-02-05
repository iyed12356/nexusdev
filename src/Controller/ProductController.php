<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/BProduct')]
final class ProductController extends AbstractController
{
    #[Route(name: 'app_product_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $products = $productRepository->findBy(['deletedAt' => null]);

        $productId = $request->query->getInt('id', 0);
        if ($productId > 0) {
            $product = $productRepository->find($productId);
            if (!$product) {
                throw $this->createNotFoundException('Product not found');
            }
        } else {
            $product = new Product();
        }

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir').'/public/uploads/products';

                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0775, true);
                }

                $extension = $imageFile->guessExtension() ?: 'bin';
                $safeName = preg_replace('~[^a-zA-Z0-9_]+~', '-', $product->getName());
                $filename = strtolower(trim($safeName, '-')).'-'.uniqid().'.'.$extension;

                $imageFile->move($uploadsDir, $filename);

                $product->setImagePath($filename);
            }

            $isNew = $product->getId() === null;
            if ($isNew) {
                $entityManager->persist($product);
            }
            $entityManager->flush();

            $this->addFlash('success', $isNew ? 'Product created successfully.' : 'Product updated successfully.');

            return $this->redirectToRoute('app_product_back', [], Response::HTTP_SEE_OTHER);
        }

        $template = $this->isGranted('ROLE_ADMIN') ? 'product/back.html.twig' : 'product/back_org.html.twig';

        return $this->render($template, [
            'products' => $products,
            'form' => $form,
            'editing' => $product->getId() !== null,
            'currentProduct' => $product,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();
            $this->addFlash('success', 'Product deleted successfully.');
        }

        return $this->redirectToRoute('app_product_back', [], Response::HTTP_SEE_OTHER);
    }
}
