<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Booking;
use App\Entity\Payment;
use App\Entity\Inventory;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $query = trim($request->query->get('q', ''));

        if (!$query) {
            return new JsonResponse([]);
        }

        $results = [];

        // 🧾 Search Bookings
        $bookings = $em->getRepository(Booking::class)->createQueryBuilder('b')
            ->where('b.eventName LIKE :q OR b.customerName LIKE :q')
            ->setParameter('q', "%$query%")
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($bookings as $b) {
            $results[] = [
                'type' => 'Booking',
                'name' => $b->getEventName(),
                'link' => $this->generateUrl('app_booking_show', ['id' => $b->getId()])
            ];
        }

        // 💳 Search Payments
        $payments = $em->getRepository(Payment::class)->createQueryBuilder('p')
            ->where('p.referenceNumber LIKE :q OR p.customerName LIKE :q')
            ->setParameter('q', "%$query%")
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($payments as $p) {
            $results[] = [
                'type' => 'Payment',
                'name' => 'Ref: ' . $p->getReferenceNumber(),
                'link' => $this->generateUrl('app_payment_show', ['id' => $p->getId()])
            ];
        }

        // 📦 Search Inventory
        $inventory = $em->getRepository(Inventory::class)->createQueryBuilder('i')
            ->where('i.itemName LIKE :q OR i.category LIKE :q')
            ->setParameter('q', "%$query%")
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($inventory as $i) {
            $results[] = [
                'type' => 'Inventory',
                'name' => $i->getItemName(),
                'link' => $this->generateUrl('app_inventory_show', ['id' => $i->getId()])
            ];
        }

        return new JsonResponse($results);
    }
}
