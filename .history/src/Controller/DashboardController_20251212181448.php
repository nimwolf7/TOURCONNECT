<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ServiceRepository;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Repository\ActivityLogRepository;
use App\Repository\InventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[IsGranted('ROLE_ADMIN')]
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
        // Basic Counts
        $totalUsers = $userRepository->count([]);
        $totalServices = $serviceRepository->count([]);
        $totalBookings = $bookingRepository->count([]);
        $totalPayments = $paymentRepository->count([]);
        $totalInventory = $inventoryRepository->count([]);

        // Total Revenue (sum of confirmed payments)
        $query = $entityManager->createQuery(
            'SELECT SUM(p.amount) FROM App\Entity\Payment p WHERE p.paymentStatus = :status'
        )->setParameter('status', 'Confirmed');
        $totalRevenue = $query->getSingleScalarResult() ?? 0;

        // Pending Bookings
        $pendingBookings = $bookingRepository->count(['status' => 'Pending']);

        // Confirmed Bookings
        $confirmedBookings = $bookingRepository->count(['status' => 'Confirmed']);

        // Cancelled Bookings
        $cancelledBookings = $bookingRepository->count(['status' => 'Cancelled']);

        // Recent Bookings (last 5)
        $recentBookings = $entityManager->createQuery(
            'SELECT b FROM App\Entity\Booking b ORDER BY b.bookingDate DESC'
        )->setMaxResults(5)->getResult();

        // Recent Payments (last 5)
        $recentPayments = $entityManager->createQuery(
            'SELECT p FROM App\Entity\Payment p ORDER BY p.paymentDate DESC'
        )->setMaxResults(5)->getResult();

        // Recent Activity Logs (last 8)
        $recentActivities = $activityLogRepository->createQueryBuilder('a')
            ->orderBy('a.timestamp', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();

        // Top 5 Services by Stock
        $topServices = $entityManager->createQuery(
            'SELECT s FROM App\Entity\Service s ORDER BY s.stock DESC'
        )->setMaxResults(5)->getResult();

        // Sample Sparkline Data
        $usersSparkline = [5, 7, 8, 10, 9, 11, 13];
        $servicesSparkline = [3, 4, 6, 8, 7, 9, 10];
        $bookingsSparkline = [2, 3, 5, 4, 6, 8, 9];
        $inventorySparkline = [15, 18, 16, 20, 19, 22, 21];
        $revenueSparkline = [1000, 1500, 1300, 1800, 2000, 2200, 2500];

        return $this->render('dashboard/index.html.twig', [
            'totalUsers' => $totalUsers,
            'totalServices' => $totalServices,
            'totalBookings' => $totalBookings,
            'totalPayments' => $totalPayments,
            'totalInventory' => $totalInventory,
            'totalRevenue' => $totalRevenue,
            'pendingBookings' => $pendingBookings,
            'confirmedBookings' => $confirmedBookings,
            'cancelledBookings' => $cancelledBookings,
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