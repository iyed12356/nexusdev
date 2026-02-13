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

        $qb = $forumPostRepository->createQueryBuilder('fp');

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('fp.title LIKE :search OR fp.content LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Sorting
        $sort = $request->query->get('sort', 'id');
        $direction = $request->query->get('direction', 'ASC');
        
        $allowedSorts = ['id', 'title', 'createdAt'];
        $allowedDirections = ['ASC', 'DESC'];
        
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }
        if (!in_array(strtoupper($direction), $allowedDirections)) {
            $direction = 'ASC';
        }
        
        $qb->orderBy('fp.' . $sort, $direction);

        // Get results manually and create pagination array
        $query = $qb->getQuery();
        $results = $query->getResult();
        
        // Use paginator with array to bypass OrderByWalker
        $pagination = $paginator->paginate(
            $results,
            $request->query->getInt('page', 1),
            10
        );

        $postId = $request->query->getInt('id', 0);
        if ($postId > 0) {
            $forumPost = $forumPostRepository->createQueryBuilder('fp')
                ->leftJoin('fp.reponses', 'r')
                ->addSelect('r')
                ->leftJoin('r.author', 'ra')
                ->addSelect('ra')
                ->where('fp.id = :id')
                ->setParameter('id', $postId)
                ->getQuery()
                ->getOneOrNullResult();
            
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
            
            // Handle image file upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir').'/public/uploads/forum';
                
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0775, true);
                }
                
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                
                $imageFile->move($uploadsDir, $newFilename);
                $forumPost->setImage('/uploads/forum/'.$newFilename);
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
            'sort' => $sort,
            'direction' => $direction,
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
