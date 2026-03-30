<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductoverviewController extends AbstractController
{
    #[Route('/productoverview', name: 'app_productoverview')]
    public function index(): Response
    {
        return $this->render('productoverview/index.html.twig', [
            'controller_name' => 'ProductoverviewController',
        ]);
    }
}
