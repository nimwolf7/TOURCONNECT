<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class RegistrationController
{
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $verificationService
    ): JsonResponse {
        $payload = $request->toArray();
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($username === '' || $email === '' || $password === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Username, email, and password are required.',
                'errors' => [
                    'username' => 'Username is required.',
                    'email' => 'Email is required.',
                    'password' => 'Password is required.',
                ],
            ], 400);
        }

        $repo = $entityManager->getRepository(User::class);
        $existingEmail = $repo->findOneBy(['email' => $email]);
        if ($existingEmail) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Email is already registered.',
                'errors' => [
                    'email' => 'Email is already registered.'
                ],
            ], 409);
        }

        $existingUsername = $repo->findOneBy(['username' => $username]);
        if ($existingUsername) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Username is already registered.',
                'errors' => [
                    'username' => 'Username is already registered.'
                ],
            ], 409);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        $verificationUrl = $verificationService->startVerification($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ],
                'verificationUrl' => $verificationUrl,
            ],
        ], 201);
    }
}
