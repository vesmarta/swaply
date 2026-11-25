<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        LoggerInterface $logger,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($userPasswordHasher->hashPassword($user, $user->getPlainPassword()));
            $user->setPlainPassword(null);
            $user->setVerificationToken(bin2hex(random_bytes(32)));
            $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+48 hours'));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->sendVerificationEmail($user, $mailer, $logger);

            return $this->redirectToRoute('app_registration_check_email');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/register/check-email', name: 'app_registration_check_email')]
    public function checkEmail(): Response
    {
        return $this->render('registration/check_email.html.twig');
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email')]
    public function verifyEmail(string $token, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'This verification link is invalid.');

            return $this->redirectToRoute('app_login');
        }

        if ($user->getVerificationTokenExpiresAt() === null || $user->getVerificationTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'This verification link has expired. Log in and request a new one.');

            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);
        $entityManager->flush();

        $this->addFlash('success', 'Your email address has been verified. You can now log in.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/resend-verification', name: 'app_resend_verification')]
    #[IsGranted('ROLE_USER')]
    public function resendVerification(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        LoggerInterface $logger,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isVerified()) {
            $this->addFlash('info', 'Your email address is already verified.');

            return $this->redirectToRoute('app_dashboard');
        }

        $user->setVerificationToken(bin2hex(random_bytes(32)));
        $user->setVerificationTokenExpiresAt(new \DateTimeImmutable('+48 hours'));
        $entityManager->flush();

        $this->sendVerificationEmail($user, $mailer, $logger);
        $this->addFlash('success', 'A new verification email has been sent to your address.');

        return $this->redirectToRoute('app_dashboard');
    }

    private function sendVerificationEmail(User $user, MailerInterface $mailer, LoggerInterface $logger): void
    {
        $verifyUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $user->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@swaply.tn', 'Swaply'))
            ->to((string) $user->getEmail())
            ->subject('Confirm your Swaply account')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verifyUrl' => $verifyUrl,
            ]);

        try {
            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $logger->error('Unable to send the verification email: '.$e->getMessage());
            $this->addFlash('warning', 'Your account was created, but the verification email could not be sent. Please use "Resend verification" from your dashboard later.');
        }
    }
}
