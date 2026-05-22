<?php

namespace App\Controller\Api;

use App\Service\EmailVerificationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class VerificationController
{
    #[Route('/verify', name: 'api_verify', methods: ['POST'])]
    public function verify(Request $request, EmailVerificationService $verificationService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        $token = trim((string) ($payload['token'] ?? ''));
        if ($token === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Verification token is required.',
                'errors' => ['token' => 'Token is required.'],
            ], 400);
        }

        $user = $verificationService->verifyToken($token);
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Verification link is invalid or expired.',
            ], 400);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'verified' => $user->isVerified(),
                ],
            ],
        ]);
    }
}
