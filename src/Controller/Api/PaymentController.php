<?php

namespace App\Controller\Api;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class PaymentController extends AbstractController
{
    #[Route('/payments', name: 'api_payments', methods: ['GET'])]
    public function list(Request $request, PaymentRepository $paymentRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $scope = strtolower((string) $request->query->get('scope', ''));
        $isStaffOrAdmin = $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF');
        $mineOnly = $scope === 'mine' || !$isStaffOrAdmin;

        $payments = $mineOnly
            ? $paymentRepository->findBy(['owner' => $user], ['id' => 'DESC'])
            : $paymentRepository->findBy([], ['id' => 'DESC']);

        $data = array_map(fn (Payment $payment) => $this->serializePayment($payment), $payments);

        return new JsonResponse([
            'success' => true,
            'message' => 'Payments fetched successfully.',
            'payments' => $data,
            'data' => $data,
            'meta' => ['count' => count($data)],
        ]);
    }

    #[Route('/payments/{id}', name: 'api_payment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, PaymentRepository $paymentRepository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $payment = $paymentRepository->find($id);
        if (!$payment) {
            return $this->jsonError('Payment not found.', 404);
        }

        if (!$this->canAccessPayment($payment, $user)) {
            return $this->jsonError('You can only access your own payments.', 403);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->serializePayment($payment),
        ]);
    }

    #[Route('/payments', name: 'api_payment_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        BookingRepository $bookingRepository
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->jsonError('Invalid JSON payload.', 400);
        }

        $bookingId = (int) ($payload['booking_id'] ?? $payload['bookingId'] ?? 0);
        if ($bookingId <= 0) {
            return $this->jsonError('A valid booking is required.', 422, ['booking_id' => 'Booking is required.']);
        }

        $booking = $bookingRepository->find($bookingId);
        if (!$booking instanceof Booking) {
            return $this->jsonError('Booking not found.', 404);
        }

        if (!$this->canAccessBooking($booking, $user)) {
            return $this->jsonError('You can only pay for your own bookings.', 403);
        }

        $existing = $entityManager->getRepository(Payment::class)->findOneBy(['booking' => $booking]);
        if ($existing) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Payment already exists for this booking.',
                'data' => $this->serializePayment($existing),
            ]);
        }

        $method = trim((string) ($payload['method'] ?? 'Cash'));
        $payment = new Payment();
        $payment->setOwner($user);
        $payment->setBooking($booking);
        $payment->setAmount((string) ($payload['amount'] ?? $booking->getTotalAmount()));
        $payment->setMethod($method !== '' ? $method : 'Cash');
        $payment->setPaymentStatus((string) ($payload['status'] ?? $payload['paymentStatus'] ?? $payload['payment_status'] ?? 'Pending'));
        $payment->setPaymentDate(new \DateTimeImmutable('now'));

        $entityManager->persist($payment);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Payment created successfully.',
            'data' => $this->serializePayment($payment),
        ], 201);
    }

    #[Route('/payments/{id}', name: 'api_payment_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $payment = $paymentRepository->find($id);
        if (!$payment) {
            return $this->jsonError('Payment not found.', 404);
        }

        if (!$this->canAccessPayment($payment, $user)) {
            return $this->jsonError('You can only update your own payments.', 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->jsonError('Invalid JSON payload.', 400);
        }

        if (isset($payload['method'])) {
            $payment->setMethod((string) $payload['method']);
        }
        $status = $payload['status'] ?? $payload['paymentStatus'] ?? $payload['payment_status'] ?? null;
        if ($status !== null) {
            $payment->setPaymentStatus((string) $status);
        }
        if (isset($payload['amount'])) {
            $payment->setAmount((string) $payload['amount']);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Payment updated successfully.',
            'data' => $this->serializePayment($payment),
        ]);
    }

    #[Route('/payments/{id}', name: 'api_payment_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->jsonError('Authentication required.', 401);
        }

        $payment = $paymentRepository->find($id);
        if (!$payment) {
            return $this->jsonError('Payment not found.', 404);
        }

        if (!$this->canAccessPayment($payment, $user)) {
            return $this->jsonError('You can only delete your own payments.', 403);
        }

        $entityManager->remove($payment);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Payment deleted successfully.',
        ]);
    }

    private function serializePayment(Payment $payment): array
    {
        $booking = $payment->getBooking();

        return [
            'id' => $payment->getId(),
            'bookingId' => $booking?->getId(),
            'amount' => $payment->getAmount(),
            'method' => $payment->getMethod(),
            'status' => $payment->getPaymentStatus(),
            'paymentDate' => $payment->getPaymentDate()?->format('c'),
            'booking' => $booking ? [
                'id' => $booking->getId(),
                'status' => $booking->getStatus(),
            ] : null,
        ];
    }

    private function canAccessPayment(Payment $payment, User $user): bool
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return true;
        }

        return $payment->getOwner() === $user;
    }

    private function canAccessBooking(Booking $booking, User $user): bool
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return true;
        }

        return $booking->getUser() === $user;
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
