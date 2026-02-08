<?php

namespace App\Controller;

use App\Entity\ForumPost;
use App\Form\ForumPostType;
use App\Repository\ForumPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/BForumPost')]
final class ForumPostController extends AbstractController
{
    #[Route(name: 'app_forum_post_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        ForumPostRepository $forumPostRepository,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $isCoachMode = $this->isGranted('ROLE_COACH') && !$this->isGranted('ROLE_ADMIN');

        $qb = $forumPostRepository->createQueryBuilder('fp')
            ->leftJoin('fp.author', 'a')
            ->addSelect('a');

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('fp.title LIKE :search OR fp.content LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10
        );

        $postId = $request->query->getInt('id', 0);
        if ($postId > 0) {
            $forumPost = $forumPostRepository->find($postId);
            if (!$forumPost) {
                throw $this->createNotFoundException('Forum post not found');
            }

            if ($isCoachMode && $forumPost->getAuthor() !== $user) {
                throw $this->createAccessDeniedException('You can only edit your own forum posts.');
            }
        } else {
            $forumPost = new ForumPost();
            if ($isCoachMode) {
                $forumPost->setAuthor($user);
            }
        }

        $form = $this->createForm(ForumPostType::class, $forumPost);
        if ($isCoachMode) {
            $form->remove('author');
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $forumPost->getId() === null;
            if ($isCoachMode) {
                $forumPost->setAuthor($user);
            }
            if ($isNew) {
                $entityManager->persist($forumPost);
            }
            $entityManager->flush();
            $this->addFlash('success', $isNew ? 'Forum post created successfully.' : 'Forum post updated successfully.');
            return $this->redirectToRoute('app_forum_post_back', [], Response::HTTP_SEE_OTHER);
        }

        $template = $isCoachMode ? 'coach/forum_post_back.html.twig' : 'forum_post/back.html.twig';

        return $this->render($template, [
            'pagination' => $pagination,
            'form' => $form,
            'editing' => $forumPost->getId() !== null,
            'currentForumPost' => $forumPost,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_forum_post_delete', methods: ['POST'])]
    public function delete(Request $request, ForumPost $forumPost, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$forumPost->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($forumPost);
            $entityManager->flush();
            $this->addFlash('success', 'Forum post deleted successfully.');
        }

        return $this->redirectToRoute('app_forum_post_back', [], Response::HTTP_SEE_OTHER);
    }
}
