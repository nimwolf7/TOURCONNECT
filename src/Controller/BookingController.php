<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/booking')]
final class BookingController extends AbstractController
{
    #[Route(name: 'app_booking_index', methods: ['GET'])]
    public function index(Request $request, BookingRepository $bookingRepository): Response
    {
        $search = $request->query->get('search');
        $status = $request->query->get('status');

        $queryBuilder = $bookingRepository->createQueryBuilder('b');

        $bookings = $queryBuilder->orderBy('b.bookingDate', 'DESC')->getQuery()->getResult();
        if ($search) {
            $searchLower = strtolower($search);
            $bookings = array_filter($bookings, function($booking) use ($searchLower) {
                $idMatch = strpos((string)$booking->getId(), $searchLower) !== false;
                $statusMatch = strpos(strtolower($booking->getStatus()), $searchLower) !== false;
                $dateMatch = $booking->getBookingDate() && strpos(strtolower($booking->getBookingDate()->format('Y-m-d H:i')), $searchLower) !== false;
                return $idMatch || $statusMatch || $dateMatch;
            });
        }

        if ($status) {
            $queryBuilder
                ->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        $bookings = $queryBuilder->orderBy('b.bookingDate', 'DESC')->getQuery()->getResult();

        return $this->render('booking/index.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/new', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Deduct stock from selected service
            $service = $booking->getService();
            $quantity = $booking->getQuantity();
            if ($service && $quantity > 0) {
                $currentStock = $service->getStock();
                $service->setStock(max(0, $currentStock - $quantity));
                $entityManager->persist($service);
            }
            $entityManager->persist($booking);
            $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Created booking #' . $booking->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            $this->addFlash('success', '✅ Booking created successfully!');
            return $this->redirectToRoute('app_booking_index');
        }

        return $this->render('booking/new.html.twig', [
            'booking' => $booking,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_booking_show', methods: ['GET'])]
    public function show(Booking $booking): Response
    {
        return $this->render('booking/show.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_booking_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Booking $booking, EntityManagerInterface $entityManager): Response
    {
        // Staff can now edit any booking, including those created by admin
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Edited booking #' . $booking->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            $this->addFlash('info', '✏️ Booking updated successfully!');
            return $this->redirectToRoute('app_booking_index');
        }

        return $this->render('booking/edit.html.twig', [
            'booking' => $booking,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_booking_delete', methods: ['POST'])]
    public function delete(Request $request, Booking $booking, EntityManagerInterface $entityManager): Response
    {
        // Restrict staff to only delete their own bookings
        $user = $this->getUser();
        if (in_array('ROLE_STAFF', $user->getRoles(), true)) {
            if ($booking->getUser() !== $user) {
                throw $this->createAccessDeniedException('You can only delete your own bookings.');
            }
        }
        if ($this->isCsrfTokenValid('delete' . $booking->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($booking);
            $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Deleted booking #' . $booking->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            $this->addFlash('danger', '🗑️ Booking deleted successfully!');
        }

        return $this->redirectToRoute('app_booking_index');
    }
}
