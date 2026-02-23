<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Form\ForgotPasswordRequestType;
use App\Form\ResetPasswordType;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

final class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetTokenRepository $resetTokenRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private TokenGeneratorInterface $tokenGenerator,
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher,
        private SluggerInterface $slugger
    ) {}

    #[Route('/forgot-password', name: 'app_forgot_password_request')]
    public function request(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_post_login_redirect');
        }

        $form = $this->createForm(ForgotPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Always show the same message to avoid user enumeration
            $this->addFlash('info', 'If an account with this email exists, a password reset link has been sent.');

            if ($user) {
                // Delete any existing tokens for this user
                $this->resetTokenRepository->deleteAllForUser($user);

                // Generate a raw token (will be sent in email)
                $rawToken = $this->tokenGenerator->generateToken();
                $tokenHash = hash('sha256', $rawToken);

                // Token valid for 30 minutes
                $expiresAt = new \DateTimeImmutable('+30 minutes');

                $resetToken = new PasswordResetToken($user, $tokenHash, $expiresAt);
                $this->resetTokenRepository->save($resetToken, true);

                // Send email
                $resetUrl = $this->urlGenerator->generate('app_reset_password', ['token' => $rawToken], UrlGeneratorInterface::ABSOLUTE_URL);

                $email = (new Email())
                    ->from($_ENV['MAILER_FROM'] ?? 'noreply@nexusplay.gg')
                    ->to($user->getEmail())
                    ->subject('Reset your NexusPlay password')
                    ->html($this->renderView('emails/reset_password.html.twig', [
                        'user' => $user,
                        'resetUrl' => $resetUrl,
                        'expiresAt' => $expiresAt,
                    ]));

                try {
                    $this->mailer->send($email);
                } catch (\Exception $e) {
                    // In production, you should log this error
                    // For now, we silently fail to avoid leaking information
                }
            }

            return $this->redirectToRoute('app_forgot_password_request');
        }

        return $this->render('security/forgot_password_request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function reset(string $token, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_post_login_redirect');
        }

        $tokenHash = hash('sha256', $token);
        $resetToken = $this->resetTokenRepository->findOneByValidToken($tokenHash);

        if (!$resetToken) {
            $this->addFlash('danger', 'Invalid or expired reset link. Please try again.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $resetToken->getUser();

            // Hash the new password
            $newPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            // Mark token as used
            $resetToken->markAsUsed();
            $this->entityManager->flush();

            $this->addFlash('success', 'Your password has been reset successfully. You can now log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }
}
