<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class IndexController
{
    #[Route('', name: 'api_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => 'API index',
            'endpoints' => [
                ['method' => 'POST', 'path' => '/api/login'],
                ['method' => 'POST', 'path' => '/api/register'],
                ['method' => 'POST', 'path' => '/api/verify'],
                ['method' => 'GET', 'path' => '/api/bookings'],
                ['method' => 'GET', 'path' => '/api/budget-tracker'],
                ['method' => 'GET', 'path' => '/api/payments'],
                ['method' => 'GET', 'path' => '/api/services'],
            ],
        ]);
    }
}
