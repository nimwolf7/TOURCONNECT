<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $mailerFrom
    ) {
    }

    public function startVerification(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setVerificationToken($token);
        $user->setIsVerified(false);
        $user->setVerifiedAt(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $message = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Verify your Tour Connect account')
            ->text(
                "Welcome to Tour Connect!\n\n" .
                "Please verify your account by opening this link:\n" .
                $verificationUrl . "\n\n" .
                "If you did not request this, you can ignore this email."
            );

        $this->mailer->send($message);

        return $verificationUrl;
    }

    public function verifyToken(string $token): ?User
    {
        if ($token === '') {
            return null;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['verificationToken' => $token]);
        if (!$user) {
            return null;
        }

        if ($user->isVerified()) {
            return $user;
        }

        $user->setIsVerified(true);
        $user->setVerifiedAt(new \DateTimeImmutable());
        $user->setVerificationToken(null);
        $this->entityManager->flush();

        return $user;
    }
}
