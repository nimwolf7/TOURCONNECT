<?php

namespace App\Controller\Api;

use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class BookingController extends AbstractController
{
    #[Route('/bookings', name: 'api_bookings', methods: ['GET'])]
    public function list(BookingRepository $bookingRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required.',
                'errors' => [
                    'authorization' => 'Missing or invalid token.'
                ],
            ], 401);
        }

        $isStaffOrAdmin = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF');
        $bookings = $isStaffOrAdmin ? $bookingRepository->findAll() : $bookingRepository->findBy(['user' => $user]);

        $data = array_map(function ($booking) {
            $service = $booking->getService();
            return [
                'id' => $booking->getId(),
                'service' => $service ? [
                    'id' => $service->getId(),
                    'title' => $service->getTitle(),
                ] : null,
                'quantity' => $booking->getQuantity(),
                'status' => $booking->getStatus(),
                'bookingDate' => $booking->getBookingDate()?->format('c'),
                'totalAmount' => $booking->getTotalAmount(),
            ];
        }, $bookings);

        return new JsonResponse([
            'success' => true,
            'message' => 'Bookings fetched successfully.',
            'data' => $data,
            'meta' => [
                'count' => count($data),
            ],
        ]);
    }
}
