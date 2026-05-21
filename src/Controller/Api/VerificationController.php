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
        $payload = $request->toArray();
        $token = trim((string) ($payload['token'] ?? ''));
        $user = $verificationService->verifyToken($token);
        if (!$user) {
            return new JsonResponse(['message' => 'Verification link is invalid or expired.'], 400);
        }

        return new JsonResponse(['message' => 'Email verified successfully.'], 200);
    }
}
