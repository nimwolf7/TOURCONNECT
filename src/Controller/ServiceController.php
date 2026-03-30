<?php

namespace App\Controller;

use App\Entity\Service;
use App\Form\ServiceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/services')]
class ServiceController extends AbstractController
{
    #[Route('/', name: 'app_service_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $query = $request->query->get('q', '');
        $repo = $em->getRepository(Service::class);

        if ($query) {
            $services = $repo->createQueryBuilder('s')
                ->where('s.title LIKE :q')
                ->orWhere('s.description LIKE :q')
                ->orWhere('s.category LIKE :q')
                ->setParameter('q', '%'.$query.'%')
                ->orderBy('s.id', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $services = $repo->findBy([], ['id' => 'DESC']);
        }

        return $this->render('service/index.html.twig', [
            'services' => $services,
        ]);
    }

    #[Route('/new', name: 'app_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            // Always assign owner
            $service->setOwner($this->getUser());

            // Validation errors
            if (!$form->isValid()) {
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }

            if ($form->isValid()) {

                // Handle image upload
                $imageFile = $form->get('imageFile')->getData();
                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = transliterator_transliterate(
                        'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
                        $originalFilename
                    );
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $imageFile->move(
                            $this->getParameter('kernel.project_dir') . '/public/images/services',
                            $newFilename
                        );
                        $service->setImage($newFilename);
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Failed to upload image.');
                    }
                }

                // Save service
                $em->persist($service);
                $em->flush();

                // Sync inventory
                $inventoryRepo = $em->getRepository(\App\Entity\Inventory::class);
                $inventory = $inventoryRepo->findOneBy(['service' => $service]) ?? new \App\Entity\Inventory();

                $inventory->setService($service);
                $inventory->setQuantityAvailable($service->getStock() ?? 0);
                $inventory->setLastUpdated(new \DateTime());

                $em->persist($inventory);
                $em->flush();

                // Log activity
                $activityLog = new \App\Entity\ActivityLog();
                $activityLog->setUser($this->getUser());
                $activityLog->setAction('Created service #' . $service->getId());
                $activityLog->setTimestamp(new \DateTime());
                $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');

                $em->persist($activityLog);
                $em->flush();

                $this->addFlash('success', 'Service added successfully!');
                return $this->redirectToRoute('app_service_index');
            }
        }

        return $this->render('service/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_service_edit')]
    public function edit(Request $request, Service $service, EntityManagerInterface $em): Response
    {
        // Staff can now edit any service, including those created by admin

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {

                // Delete old file
                if ($service->getImage()) {
                    $oldImagePath = $this->getParameter('kernel.project_dir') . '/public/images/services/' . $service->getImage();
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/images/services',
                        $newFilename
                    );
                    $service->setImage($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Failed to upload image.');
                }
            }

            $em->flush();

            // Inventory sync
            $inventoryRepo = $em->getRepository(\App\Entity\Inventory::class);
            $inventory = $inventoryRepo->findOneBy(['service' => $service]) ?? new \App\Entity\Inventory();

            $inventory->setService($service);
            $inventory->setQuantityAvailable($service->getStock() ?? 0);
            $inventory->setLastUpdated(new \DateTime());

            $em->persist($inventory);
            $em->flush();

            // Log
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Edited service #' . $service->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $em->persist($activityLog);
            $em->flush();

            $this->addFlash('success', 'Service updated successfully!');
            return $this->redirectToRoute('app_service_index');
        }

        return $this->render('service/edit.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
        ]);
    }

    #[Route('/{id}', name: 'app_service_show')]
    public function show(Service $service): Response
    {
        return $this->render('service/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_service_delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $em): Response
    {
        // Access check
        if ($this->isGranted('ROLE_STAFF') && $service->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only delete your own services.');
        }

        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {

            $serviceId = $service->getId();

            $em->remove($service);
            $em->flush();

            // Log deletion
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Deleted service #' . $serviceId);
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');

            $em->persist($activityLog);
            $em->flush();

            $this->addFlash('success', 'Service deleted successfully!');
        }

        return $this->redirectToRoute('app_service_index');
    }
}
