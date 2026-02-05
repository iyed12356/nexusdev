<?php

namespace App\Controller;

use App\Entity\Content;
use App\Form\ContentType;
use App\Repository\ContentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/BContent')]
final class ContentController extends AbstractController
{
    #[Route(name: 'app_content_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        ContentRepository $contentRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $contents = $contentRepository->findAll();

        $contentId = $request->query->getInt('id', 0);
        if ($contentId > 0) {
            $content = $contentRepository->find($contentId);
            if (!$content) {
                throw $this->createNotFoundException('Content not found');
            }
        } else {
            $content = new Content();
        }

        $form = $this->createForm(ContentType::class, $content);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $content->getId() === null;
            if ($isNew) {
                $entityManager->persist($content);
            }
            $entityManager->flush();

            $this->addFlash('success', $isNew ? 'Content created successfully.' : 'Content updated successfully.');

            return $this->redirectToRoute('app_content_back', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('content/back.html.twig', [
            'contents' => $contents,
            'form' => $form,
            'editing' => $content->getId() !== null,
            'currentContent' => $content,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_content_delete', methods: ['POST'])]
    public function delete(Request $request, Content $content, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$content->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($content);
            $entityManager->flush();
            $this->addFlash('success', 'Content deleted successfully.');
        }

        return $this->redirectToRoute('app_content_back', [], Response::HTTP_SEE_OTHER);
    }
}
