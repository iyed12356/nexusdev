<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Form\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/BNotification')]
final class NotificationController extends AbstractController
{
    #[Route(name: 'app_notification_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $notifications = $notificationRepository->findAll();

        $notificationId = $request->query->getInt('id', 0);
        if ($notificationId > 0) {
            $notification = $notificationRepository->find($notificationId);
            if (!$notification) {
                throw $this->createNotFoundException('Notification not found');
            }
        } else {
            $notification = new Notification();
        }

        $form = $this->createForm(NotificationType::class, $notification);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $notification->getId() === null;
            if ($isNew) {
                $entityManager->persist($notification);
            }
            $entityManager->flush();

            $this->addFlash('success', $isNew ? 'Notification created successfully.' : 'Notification updated successfully.');

            return $this->redirectToRoute('app_notification_back', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('notification/back.html.twig', [
            'notifications' => $notifications,
            'form' => $form,
            'editing' => $notification->getId() !== null,
            'currentNotification' => $notification,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_notification_delete', methods: ['POST'])]
    public function delete(Request $request, Notification $notification, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$notification->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($notification);
            $entityManager->flush();
            $this->addFlash('success', 'Notification deleted successfully.');
        }

        return $this->redirectToRoute('app_notification_back', [], Response::HTTP_SEE_OTHER);
    }
}
