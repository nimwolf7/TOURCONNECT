<?php

namespace App\Controller\Api;

use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PaymentController extends AbstractController
{
    #[Route('/payments', name: 'api_payments', methods: ['GET'])]
    public function list(PaymentRepository $paymentRepository): JsonResponse
    {
        $user = $this->getUser();
        $isStaffOrAdmin = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF');
        $payments = $isStaffOrAdmin ? $paymentRepository->findAll() : $paymentRepository->findBy(['owner' => $user]);

        $data = array_map(function ($payment) {
            $booking = $payment->getBooking();
            return [
                'id' => $payment->getId(),
                'bookingId' => $booking?->getId(),
                'amount' => $payment->getAmount(),
                'method' => $payment->getMethod(),
                'status' => $payment->getPaymentStatus(),
                'paymentDate' => $payment->getPaymentDate()?->format('c'),
            ];
        }, $payments);

        return new JsonResponse([
            'success' => true,
            'message' => 'Payments fetched successfully.',
            'data' => $data,
            'meta' => [
                'count' => count($data),
            ],
        ]);
    }
}
