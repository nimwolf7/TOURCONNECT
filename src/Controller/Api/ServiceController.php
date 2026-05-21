<?php

namespace App\Controller\Api;

use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ServiceController extends AbstractController
{
    #[Route('/services', name: 'api_services', methods: ['GET'])]
    public function list(ServiceRepository $serviceRepository, Request $request): JsonResponse
    {
        $services = $serviceRepository->findAll();
        $baseUrl = $request->getSchemeAndHttpHost();

        $data = array_map(function ($service) use ($baseUrl) {
            $image = $service->getImage();
            return [
                'id' => $service->getId(),
                'title' => $service->getTitle(),
                'description' => $service->getDescription(),
                'price' => $service->getPrice(),
                'category' => $service->getCategory(),
                'slots' => $service->getSlots(),
                'dateAdded' => $service->getDateAdded(),
                'imageUrl' => $image ? $baseUrl . '/images/services/' . $image : null,
            ];
        }, $services);

        return new JsonResponse([
            'success' => true,
            'message' => 'Services fetched successfully.',
            'data' => $data,
            'meta' => [
                'count' => count($data),
            ],
        ]);
    }
}
