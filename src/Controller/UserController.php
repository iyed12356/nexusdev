<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/BUser')]
final class UserController extends AbstractController
{
    #[Route(name: 'app_user_back', methods: ['GET', 'POST'])]
    public function back(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $users = $userRepository->findAll();

        $userId = $request->query->getInt('id', 0);
        if ($userId > 0) {
            $user = $userRepository->find($userId);
            if (!$user) {
                throw $this->createNotFoundException('User not found');
            }
        } else {
            $user = new User();
        }

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isNew = $user->getId() === null;
            if ($isNew) {
                $entityManager->persist($user);
            }
            $entityManager->flush();

            $this->addFlash('success', $isNew ? 'User created successfully.' : 'User updated successfully.');

            return $this->redirectToRoute('app_user_back', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/back.html.twig', [
            'users' => $users,
            'form' => $form,
            'editing' => $user->getId() !== null,
            'currentUser' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('app_user_back', [], Response::HTTP_SEE_OTHER);
    }
}
