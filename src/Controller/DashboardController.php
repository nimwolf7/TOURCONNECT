<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ServiceRepository;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Repository\ActivityLogRepository;
use App\Repository\InventoryRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        BookingRepository $bookingRepository,
        PaymentRepository $paymentRepository,
        ActivityLogRepository $activityLogRepository,
        InventoryRepository $inventoryRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        $isCustomerOnly = $currentUser instanceof User
            && !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('ROLE_STAFF');

        // Basic Counts
        $totalUsers = $isCustomerOnly ? 0 : $userRepository->count([]);
        $totalServices = $serviceRepository->count([]);
        $totalInventory = $isCustomerOnly ? 0 : $inventoryRepository->count([]);

        if ($isCustomerOnly && $currentUser instanceof User) {
            $totalBookings = $bookingRepository->count(['user' => $currentUser]);
            $totalPayments = $paymentRepository->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->join('p.booking', 'b')
                ->where('b.user = :user')
                ->setParameter('user', $currentUser)
                ->getQuery()
                ->getSingleScalarResult();

            $totalRevenue = $paymentRepository->createQueryBuilder('p')
                ->select('COALESCE(SUM(p.amount), 0)')
                ->join('p.booking', 'b')
                ->where('b.user = :user')
                ->andWhere('p.paymentStatus = :status')
                ->setParameter('user', $currentUser)
                ->setParameter('status', 'Confirmed')
                ->getQuery()
                ->getSingleScalarResult();

            $pendingBookings = $bookingRepository->count(['user' => $currentUser, 'status' => 'Pending']);
            $confirmedBookings = $bookingRepository->count(['user' => $currentUser, 'status' => 'Complete']);
            $cancelledBookings = $bookingRepository->count(['user' => $currentUser, 'status' => 'Cancelled']);
            $refundedBookings = $bookingRepository->count(['user' => $currentUser, 'status' => 'Refund']);

            $recentBookings = $bookingRepository->createQueryBuilder('b')
                ->where('b.user = :user')
                ->setParameter('user', $currentUser)
                ->orderBy('b.bookingDate', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            $recentPayments = $paymentRepository->createQueryBuilder('p')
                ->join('p.booking', 'b')
                ->where('b.user = :user')
                ->setParameter('user', $currentUser)
                ->orderBy('p.paymentDate', 'DESC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        } else {
            $totalBookings = $bookingRepository->count([]);
            $totalPayments = $paymentRepository->count([]);
            $totalRevenue = $entityManager->createQuery(
                'SELECT SUM(p.amount) FROM App\Entity\Payment p WHERE p.paymentStatus = :status'
            )->setParameter('status', 'Confirmed')->getSingleScalarResult() ?? 0;

            $pendingBookings = $bookingRepository->count(['status' => 'Pending']);
            $confirmedBookings = $bookingRepository->count(['status' => 'Complete']);
            $cancelledBookings = $bookingRepository->count(['status' => 'Cancelled']);
            $refundedBookings = $bookingRepository->count(['status' => 'Refund']);

            $recentBookings = $entityManager->createQuery(
                'SELECT b FROM App\Entity\Booking b ORDER BY b.bookingDate DESC'
            )->setMaxResults(5)->getResult();

            $recentPayments = $entityManager->createQuery(
                'SELECT p FROM App\Entity\Payment p ORDER BY p.paymentDate DESC'
            )->setMaxResults(5)->getResult();
        }

        // Recent Activity Logs (last 8)
        $recentActivities = $activityLogRepository->createQueryBuilder('a')
            ->orderBy('a.timestamp', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        // Top 5 Services by Slots
        $topServices = $entityManager->createQuery(
            'SELECT s FROM App\Entity\Service s ORDER BY s.slots DESC'
        )->setMaxResults(5)->getResult();

        // Sample Sparkline Data
        $usersSparkline = [5, 7, 8, 10, 9, 11, 13];
        $servicesSparkline = [3, 4, 6, 8, 7, 9, 10];
        $bookingsSparkline = [2, 3, 5, 4, 6, 8, 9];
        $inventorySparkline = [15, 18, 16, 20, 19, 22, 21];
        $revenueSparkline = [1000, 1500, 1300, 1800, 2000, 2200, 2500];

        return $this->render('dashboard/index.html.twig', [
            'isCustomerOnly' => $isCustomerOnly,
            'totalUsers' => $totalUsers,
            'totalServices' => $totalServices,
            'totalBookings' => $totalBookings,
            'totalPayments' => $totalPayments,
            'totalInventory' => $totalInventory,
            'totalRevenue' => $totalRevenue,
            'pendingBookings' => $pendingBookings,
            'confirmedBookings' => $confirmedBookings,
            'cancelledBookings' => $cancelledBookings,
            'refundedBookings' => $refundedBookings,
            'recentBookings' => $recentBookings,
            'recentPayments' => $recentPayments,
            'recentActivities' => $recentActivities,
            'topServices' => $topServices,
            'usersSparkline' => $usersSparkline,
            'servicesSparkline' => $servicesSparkline,
            'bookingsSparkline' => $bookingsSparkline,
            'inventorySparkline' => $inventorySparkline,
            'revenueSparkline' => $revenueSparkline,
        ]);
    }
}