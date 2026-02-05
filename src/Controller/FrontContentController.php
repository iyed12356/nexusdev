<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Content;
use App\Entity\ForumPost;
use App\Repository\ContentRepository;
use App\Repository\ForumPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route('/FContent')]
final class FrontContentController extends AbstractController
{
    #[Route(name: 'front_content_index', methods: ['GET'])]
    public function index(ContentRepository $contentRepository): Response
    {
        $contents = $contentRepository->findBy(['deletedAt' => null], ['createdAt' => 'DESC']);

        return $this->render('front/content/index.html.twig', [
            'contents' => $contents,
        ]);
    }

    #[Route('/{id}', name: 'front_content_show', methods: ['GET', 'POST'])]
    public function show(
        Content $content,
        ContentRepository $contentRepository,
        ForumPostRepository $forumPostRepository,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (method_exists($content, 'getDeletedAt') && $content->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Content not found');
        }

        $popularContents = $contentRepository->findBy(['deletedAt' => null], ['createdAt' => 'DESC'], 5);

        // Back guide comments with a dedicated forum thread identified by a special title
        $threadTitle = sprintf('[GUIDE #%d] %s', $content->getId(), $content->getTitle());
        $thread = $forumPostRepository->findOneBy(['title' => $threadTitle]);
        $comments = $thread ? $thread->getComments() : [];
        $commentFormView = null;

        $user = $this->getUser();
        if ($user) {
            $formBuilder = $this->createFormBuilder();
            $formBuilder->add('content', TextareaType::class, [
                'label' => 'Add a comment',
                'constraints' => [
                    new NotBlank(['message' => 'Comment cannot be empty']),
                    new Length([
                        'min' => 3,
                        'max' => 1000,
                        'minMessage' => 'Comment must be at least {{ limit }} characters',
                        'maxMessage' => 'Comment cannot exceed {{ limit }} characters',
                    ]),
                ],
                'attr' => [
                    'rows' => 4,
                    'class' => 'form-control',
                    'placeholder' => 'Share your thoughts on this guide...'
                ],
            ]);

            $form = $formBuilder->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Lazily create the forum thread for this guide if it doesn't exist yet
                if (!$thread) {
                    $thread = new ForumPost();
                    $thread->setTitle($threadTitle);
                    $thread->setContent(sprintf('Discussion thread for guide #%d', $content->getId()));
                    $thread->setAuthor($user);
                    $entityManager->persist($thread);
                }

                $data = $form->getData();
                $comment = new Comment();
                $comment->setAuthor($user);
                $comment->setPost($thread);
                $comment->setContent($data['content']);

                $entityManager->persist($comment);
                $entityManager->flush();

                $this->addFlash('success', 'Comment added to this guide.');

                return $this->redirectToRoute('front_content_show', ['id' => $content->getId()]);
            }

            $commentFormView = $form->createView();

            // Refresh comments from persisted thread
            if ($thread) {
                $comments = $thread->getComments();
            }
        }

        return $this->render('front/content/show.html.twig', [
            'content' => $content,
            'popularContents' => $popularContents,
            'comments' => $comments,
            'commentForm' => $commentFormView,
        ]);
    }
}
