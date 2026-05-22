<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
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
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $search = $request->query->get('search');
        $status = $request->query->get('status');

        $queryBuilder = $bookingRepository->createQueryBuilder('b');
        if ($this->isCustomerOnly($currentUser) && $currentUser instanceof User) {
            $queryBuilder
                ->andWhere('b.user = :currentUser')
                ->setParameter('currentUser', $currentUser);
        }

        if ($status) {
            $queryBuilder
                ->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        $bookings = $queryBuilder->orderBy('b.bookingDate', 'DESC')->getQuery()->getResult();

        if ($search) {
            $searchLower = strtolower($search);
            $bookings = array_filter($bookings, function ($booking) use ($searchLower) {
                $idMatch = strpos((string) $booking->getId(), $searchLower) !== false;
                $statusMatch = strpos(strtolower((string) $booking->getStatus()), $searchLower) !== false;
                $dateMatch = $booking->getBookingDate() && strpos(strtolower($booking->getBookingDate()->format('Y-m-d H:i')), $searchLower) !== false;

                return $idMatch || $statusMatch || $dateMatch;
            });
        }

        return $this->render('booking/index.html.twig', [
            'bookings' => $bookings,
        ]);
    }

    #[Route('/new', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isCustomerOnly($currentUser) && $currentUser instanceof User) {
                $booking->setUser($currentUser);
                $booking->setStatus('Pending');
            }
            // Deduct slots from selected service
            $service = $booking->getService();
            $quantity = $booking->getQuantity();
            if ($service && $quantity > 0) {
                $booking->setTotalAmount(number_format(((float) $service->getPrice()) * $quantity, 2, '.', ''));
                $currentSlots = $service->getSlots() ?? 0;
                $service->setSlots(max(0, $currentSlots - $quantity));
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
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($this->isCustomerOnly($currentUser) && $currentUser instanceof User && $booking->getUser() !== $currentUser) {
            throw $this->createAccessDeniedException('You can only view your own bookings.');
        }

        return $this->render('booking/show.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_booking_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Booking $booking, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if ($this->isCustomerOnly($currentUser) && $currentUser instanceof User && $booking->getUser() !== $currentUser) {
            throw $this->createAccessDeniedException('You can only edit your own bookings.');
        }

        $originalStatus = $booking->getStatus();
        // Staff can now edit any booking, including those created by admin
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->isCustomerOnly($currentUser) && $currentUser instanceof User) {
                $booking->setUser($currentUser);
                $booking->setStatus($originalStatus ?? 'Pending');
                $this->addFlash('info', 'Only admins can update booking status.');
            }
            $service = $booking->getService();
            $quantity = $booking->getQuantity();
            if ($service && $quantity > 0) {
                $booking->setTotalAmount(number_format(((float) $service->getPrice()) * $quantity, 2, '.', ''));
            }
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
            return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
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
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Only logged in users can delete bookings.');
        }
        if ($this->isCustomerOnly($user) && $user instanceof User && $booking->getUser() !== $user) {
            throw $this->createAccessDeniedException('You can only delete your own bookings.');
        }
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

    private function isCustomerOnly(?User $user): bool
    {
        return $user instanceof User
            && !in_array('ROLE_ADMIN', $user->getRoles(), true)
            && !in_array('ROLE_STAFF', $user->getRoles(), true);
    }
}
