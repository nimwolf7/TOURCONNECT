<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\BudgetTrackerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class BudgetTrackerController extends AbstractController
{
    #[Route('/budget-tracker', name: 'api_budget_tracker', methods: ['GET'])]
    public function list(BudgetTrackerRepository $budgetTrackerRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $isStaffOrAdmin = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF');
        $items = $isStaffOrAdmin ? $budgetTrackerRepository->findAll() : $budgetTrackerRepository->findBy(['user' => $user]);

        $data = array_map(function ($item) {
            $user = $item->getUser();
            $booking = $item->getBooking();
            return [
                'id' => $item->getId(),
                'booking' => $booking ? [
                    'id' => $booking->getId(),
                    'service' => $booking->getService()?->getTitle(),
                ] : null,
                'user' => $user ? [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                ] : null,
                'category' => $item->getCategory(),
                'amountPlanned' => $item->getAmountPlanned(),
                'amountSpent' => $item->getAmountSpent(),
                'dateRange' => $item->getDateRange(),
            ];
        }, $items);

        return new JsonResponse([
            'success' => true,
            'message' => 'Budget tracker entries fetched successfully.',
            'data' => $data,
            'meta' => [
                'count' => count($data),
            ],
        ]);
    }
}
