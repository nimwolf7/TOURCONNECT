<?php

namespace App\Controller\Api;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class BookingController extends AbstractController
{
    #[Route('/bookings', name: 'api_bookings', methods: ['GET'])]
    public function list(Request $request, BookingRepository $bookingRepository, PaymentRepository $paymentRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $scope = strtolower((string) $request->query->get('scope', ''));
        $isStaffOrAdmin = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF');
        $mineOnly = $scope === 'mine' || !$isStaffOrAdmin;

        $bookings = $mineOnly
            ? $bookingRepository->findBy(['user' => $user], ['bookingDate' => 'DESC'])
            : $bookingRepository->findBy([], ['bookingDate' => 'DESC']);

        $paymentsByBookingId = [];
        foreach ($paymentRepository->findAll() as $payment) {
            $bookingId = $payment->getBooking()?->getId();
            if ($bookingId !== null) {
                $paymentsByBookingId[$bookingId] = $payment;
            }
        }

        $data = array_map(function (Booking $booking) use ($paymentsByBookingId) {
            $service = $booking->getService();
            $payment = $paymentsByBookingId[$booking->getId()] ?? null;

            return $this->serializeBooking($booking, $service, $payment);
        }, $bookings);

        return new JsonResponse([
            'success' => true,
            'message' => 'Bookings fetched successfully.',
            'bookings' => $data,
            'data' => $data,
            'meta' => ['count' => count($data)],
        ]);
    }

    #[Route('/bookings/{id}', name: 'api_booking_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, BookingRepository $bookingRepository, PaymentRepository $paymentRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $booking = $bookingRepository->find($id);
        if (!$booking) {
            return $this->jsonError('Booking not found.', 404);
        }

        if (!$this->canAccessBooking($booking, $user)) {
            return $this->jsonError('You can only access your own bookings.', 403);
        }

        $payment = $paymentRepository->findOneBy(['booking' => $booking]);

        return new JsonResponse([
            'success' => true,
            'data' => $this->serializeBooking($booking, $booking->getService(), $payment),
        ]);
    }

    #[Route('/bookings', name: 'api_booking_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ServiceRepository $serviceRepository
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->jsonError('Invalid JSON payload.', 400);
        }

        $serviceId = (int) ($payload['service_id'] ?? $payload['serviceId'] ?? $payload['product_id'] ?? $payload['productId'] ?? 0);
        $quantity = (int) ($payload['quantity'] ?? 0);
        $paymentMethod = trim((string) ($payload['payment_method'] ?? $payload['paymentMethod'] ?? 'Cash'));

        if ($serviceId <= 0) {
            return $this->jsonError('A valid service is required.', 422, ['service_id' => 'Service is required.']);
        }

        if ($quantity <= 0) {
            return $this->jsonError('Quantity must be at least 1.', 422, ['quantity' => 'Quantity must be at least 1.']);
        }

        /** @var Service|null $service */
        $service = $serviceRepository->find($serviceId);
        if (!$service) {
            return $this->jsonError('Service not found.', 404);
        }

        $availableSlots = (int) ($service->getSlots() ?? 0);
        if ($quantity > $availableSlots) {
            return $this->jsonError('Not enough seats available for this service.', 422, [
                'quantity' => sprintf('Only %d seat(s) available.', $availableSlots),
            ]);
        }

        $unitPrice = (float) $service->getPrice();
        $totalAmount = $payload['total_amount'] ?? $payload['totalAmount'] ?? null;
        $totalAmount = $totalAmount !== null
            ? number_format((float) $totalAmount, 2, '.', '')
            : number_format($unitPrice * $quantity, 2, '.', '');

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setService($service);
        $booking->setQuantity($quantity);
        $booking->setStatus((string) ($payload['status'] ?? 'Pending'));
        $booking->setBookingDate(new \DateTime('now'));
        $booking->setTotalAmount($totalAmount);

        $service->setSlots(max(0, $availableSlots - $quantity));

        $payment = new Payment();
        $payment->setOwner($user);
        $payment->setBooking($booking);
        $payment->setAmount($totalAmount);
        $payment->setMethod($paymentMethod !== '' ? $paymentMethod : 'Cash');
        $payment->setPaymentStatus($this->resolveInitialPaymentStatus($paymentMethod));
        $payment->setPaymentDate(new \DateTimeImmutable('now'));

        $entityManager->persist($service);
        $entityManager->persist($booking);
        $entityManager->persist($payment);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Booking created successfully.',
            'data' => $this->serializeBooking($booking, $service, $payment),
        ], 201);
    }

    #[Route('/bookings/{id}', name: 'api_booking_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        BookingRepository $bookingRepository,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager,
        ServiceRepository $serviceRepository
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $booking = $bookingRepository->find($id);
        if (!$booking) {
            return $this->jsonError('Booking not found.', 404);
        }

        if (!$this->canAccessBooking($booking, $user)) {
            return $this->jsonError('You can only update your own bookings.', 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->jsonError('Invalid JSON payload.', 400);
        }

        $previousStatus = $booking->getStatus();
        $newStatus = $payload['status'] ?? null;

        if ($newStatus !== null) {
            $booking->setStatus((string) $newStatus);
        }

        if (
            $newStatus !== null
            && strcasecmp((string) $newStatus, 'Cancelled') === 0
            && strcasecmp((string) $previousStatus, 'Cancelled') !== 0
        ) {
            $this->restoreServiceSlots($booking, $serviceRepository, $entityManager);
        }

        if (isset($payload['quantity'])) {
            $booking->setQuantity((int) $payload['quantity']);
        }

        if (isset($payload['total_amount']) || isset($payload['totalAmount'])) {
            $booking->setTotalAmount((string) ($payload['total_amount'] ?? $payload['totalAmount']));
        }

        $payment = $paymentRepository->findOneBy(['booking' => $booking]);
        if ($payment && isset($payload['payment_method'])) {
            $payment->setMethod((string) $payload['payment_method']);
        }
        if ($payment && isset($payload['payment_status'])) {
            $payment->setPaymentStatus((string) $payload['payment_status']);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Booking updated successfully.',
            'data' => $this->serializeBooking($booking, $booking->getService(), $payment),
        ]);
    }

    #[Route('/bookings/{id}', name: 'api_booking_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        BookingRepository $bookingRepository,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager,
        ServiceRepository $serviceRepository
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $booking = $bookingRepository->find($id);
        if (!$booking) {
            return $this->jsonError('Booking not found.', 404);
        }

        if (!$this->canAccessBooking($booking, $user)) {
            return $this->jsonError('You can only delete your own bookings.', 403);
        }

        if (strcasecmp((string) $booking->getStatus(), 'Cancelled') !== 0) {
            $this->restoreServiceSlots($booking, $serviceRepository, $entityManager);
        }

        $payment = $paymentRepository->findOneBy(['booking' => $booking]);
        if ($payment) {
            $entityManager->remove($payment);
        }

        $entityManager->remove($booking);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Booking deleted successfully.',
        ]);
    }

    private function serializeBooking(Booking $booking, ?Service $service, ?Payment $payment): array
    {
        $user = $booking->getUser();

        return [
            'id' => $booking->getId(),
            'bookingNumber' => sprintf('BKG-%d', $booking->getId()),
            'service' => $service ? [
                'id' => $service->getId(),
                'title' => $service->getTitle(),
                'name' => $service->getTitle(),
            ] : null,
            'serviceId' => $service?->getId(),
            'quantity' => $booking->getQuantity(),
            'status' => $booking->getStatus(),
            'bookingDate' => $booking->getBookingDate()?->format('c'),
            'createdAt' => $booking->getBookingDate()?->format('c'),
            'totalAmount' => $booking->getTotalAmount(),
            'travelerName' => $user?->getUsername(),
            'travelerEmail' => $user?->getEmail(),
            'payment' => $payment ? [
                'id' => $payment->getId(),
                'method' => $payment->getMethod(),
                'status' => $payment->getPaymentStatus(),
                'amount' => $payment->getAmount(),
                'paymentDate' => $payment->getPaymentDate()?->format('c'),
            ] : null,
            'paymentMethod' => $payment?->getMethod(),
            'paymentStatus' => $payment?->getPaymentStatus(),
        ];
    }

    private function resolveInitialPaymentStatus(string $method): string
    {
        return match (strtolower($method)) {
            'gcash', 'card', 'paymongo' => 'Pending',
            default => 'Paid',
        };
    }

    private function canAccessBooking(Booking $booking, User $user): bool
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return true;
        }

        return $booking->getUser() === $user;
    }

    private function restoreServiceSlots(
        Booking $booking,
        ServiceRepository $serviceRepository,
        EntityManagerInterface $entityManager
    ): void {
        $service = $booking->getService();
        if (!$service) {
            return;
        }

        $service->setSlots(((int) ($service->getSlots() ?? 0)) + ((int) ($booking->getQuantity() ?? 0)));
        $entityManager->persist($service);
    }

    private function jsonError(string $message, int $status, array $errors = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
