<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Form\InventoryType;
use App\Repository\InventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventory')]
final class InventoryController extends AbstractController
{
    #[Route(name: 'app_inventory_index', methods: ['GET'])]
    public function index(InventoryRepository $inventoryRepository): Response
    {
        return $this->render('inventory/index.html.twig', [
            'inventories' => $inventoryRepository->findAll(),
        ]);
    }

    #[Route('/new/{serviceId}', name: 'app_inventory_new', methods: ['GET', 'POST'], requirements: ['serviceId' => '\\d+'], defaults: ['serviceId' => null])]
    public function new(Request $request, EntityManagerInterface $entityManager, int $serviceId = null): Response
    {
        $inventory = new Inventory();
        $service = null;
        if ($serviceId) {
            $service = $entityManager->getRepository(\App\Entity\Service::class)->find($serviceId);
            if ($service) {
                $inventory->setService($service);
            }
        }
        $form = $this->createForm(InventoryType::class, $inventory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($inventory);
            $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Created inventory #' . $inventory->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            return $this->redirectToRoute('app_inventory_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('inventory/new.html.twig', [
            'inventory' => $inventory,
            'form' => $form,
            'service' => $service,
        ]);
    }

    #[Route('/{id}', name: 'app_inventory_show', methods: ['GET'])]
    public function show(Inventory $inventory): Response
    {
        return $this->render('inventory/show.html.twig', [
            'inventory' => $inventory,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_inventory_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Inventory $inventory, EntityManagerInterface $entityManager): Response
    {
        // Staff can now edit any inventory, including those created by admin
        $form = $this->createForm(InventoryType::class, $inventory);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Update the related service slots
            $service = $inventory->getService();
            if ($service) {
                $service->setSlots($inventory->getQuantityAvailable());
            }
            $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Edited inventory #' . $inventory->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            return $this->redirectToRoute('app_inventory_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('inventory/edit.html.twig', [
            'inventory' => $inventory,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_inventory_delete', methods: ['POST'])]
    public function delete(Request $request, Inventory $inventory, EntityManagerInterface $entityManager): Response
    {
        // Restrict staff to only delete their own inventory (by service owner)
        $user = $this->getUser();
        if (in_array('ROLE_STAFF', $user->getRoles(), true)) {
            $service = $inventory->getService();
            if (!$service || $service->getOwner() !== $user) {
                throw $this->createAccessDeniedException('You can only delete inventory for your own services.');
            }
        }
        if ($this->isCsrfTokenValid('delete'.$inventory->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($inventory);
            $entityManager->flush();
            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Deleted inventory #' . $inventory->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_inventory_index', [], Response::HTTP_SEE_OTHER);
    }
}
