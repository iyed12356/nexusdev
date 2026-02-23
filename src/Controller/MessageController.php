<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageType;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/messages')]
final class MessageController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private MessageRepository $messageRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'app_messages')]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversations = $this->conversationRepository->findConversationsForUser($user);
        $unreadCount = $this->messageRepository->countUnreadMessagesForUser($user);
        $user->setUnreadMessageCount($unreadCount);

        if ($conversations === []) {
            $this->addFlash('danger', 'No conversations found for your account yet.');
        }

        return $this->render('message/index_simple_fixed.html.twig', [
            'conversations' => $conversations,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/conversation/{id}', name: 'app_message_conversation')]
    #[IsGranted('ROLE_USER')]
    public function conversation(Conversation $conversation): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$conversation->getParticipants()->contains($user)) {
            throw $this->createAccessDeniedException('You are not a participant of this conversation.');
        }

        $messages = $this->messageRepository->findMessagesInConversation($conversation);
        $this->messageRepository->markMessagesAsReadInConversation($conversation, $user);

        $form = $this->createForm(MessageType::class);

        return $this->render('message/conversation.html.twig', [
            'conversation' => $conversation,
            'messages' => $messages,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/send/{conversationId}', name: 'app_message_send', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function send(int $conversationId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $conversation = $this->conversationRepository->find($conversationId);

        if (!$conversation || !$conversation->getParticipants()->contains($user)) {
            return new JsonResponse(['error' => 'Invalid conversation'], 403);
        }

        $content = $request->request->get('content');
        if (empty($content)) {
            return new JsonResponse(['error' => 'Message cannot be empty'], 400);
        }

        $message = new Message($conversation, $user, $content);
        $this->messageRepository->save($message, true);

        return new JsonResponse([
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'sender' => $message->getSender()->getUsername(),
            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/conversation/{id}/send', name: 'app_message_send_form', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sendForm(Conversation $conversation, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$conversation->getParticipants()->contains($user)) {
            throw $this->createAccessDeniedException('You are not a participant of this conversation.');
        }

        $tokenId = 'message_send_' . $conversation->getId();
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid request. Please try again.');
            return $this->redirectToRoute('app_message_conversation', ['id' => $conversation->getId()]);
        }

        $content = trim((string) $request->request->get('content'));
        if ($content === '') {
            $this->addFlash('danger', 'Message cannot be empty.');
            return $this->redirectToRoute('app_message_conversation', ['id' => $conversation->getId()]);
        }

        $conversation->touch();
        $message = new Message($conversation, $user, $content);
        $this->messageRepository->save($message, true);

        return $this->redirectToRoute('app_message_conversation', ['id' => $conversation->getId()]);
    }

    #[Route('/new/{userId}', name: 'app_message_new')]
    #[IsGranted('ROLE_USER')]
    public function new(int $userId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $recipient = $this->userRepository->find($userId);

        if (!$recipient) {
            throw $this->createNotFoundException('User not found');
        }

        if ($user === $recipient) {
            throw $this->createAccessDeniedException('You cannot start a conversation with yourself.');
        }

        $conversation = $this->conversationRepository->findConversationBetweenUsers($user, $recipient);

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->addParticipant($user);
            $conversation->addParticipant($recipient);
            $this->conversationRepository->save($conversation, true);
        }

        return $this->redirectToRoute('app_message_conversation', ['id' => $conversation->getId()]);
    }

    #[Route('/search-users', name: 'app_message_search_users')]
    #[IsGranted('ROLE_USER')]
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query')
            ->andWhere('u.id != :currentUserId')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('currentUserId', $user->getId())
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $data = array_map(fn(User $u) => [
            'id' => $u->getId(),
            'username' => $u->getUsername(),
        ], $users);

        return new JsonResponse($data);
    }
}
