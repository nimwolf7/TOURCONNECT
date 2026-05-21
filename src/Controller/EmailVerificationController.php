<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyEmail(Request $request, EmailVerificationService $verificationService): RedirectResponse
    {
        $token = (string) $request->query->get('token', '');
        $user = $verificationService->verifyToken($token);
        if (!$user) {
            $this->addFlash('danger', 'Verification link is invalid or expired.');
            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Email verified successfully. You can now sign in.');
        return $this->redirectToRoute('app_login');
    }
}
