<?php

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\BudgetTracker;
use App\Entity\Inventory;
use App\Entity\Payment;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SampleDataFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var User $admin */
        $admin = $this->getReference('user_admin', User::class);
        /** @var User $staff */
        $staff = $this->getReference('user_staff', User::class);

        $serviceSeed = [
            ['Island Hopping Adventure', 'A full-day island hopping experience with snorkel gear.', '2999.00', 'Adventure', 25],
            ['City Heritage Tour', 'Guided tour through historic landmarks and museums.', '1499.00', 'Culture', 40],
            ['Sunset Yacht Cruise', 'Evening cruise with light dinner and live music.', '4999.00', 'Luxury', 12],
            ['Mountain Eco Trek', 'Half-day trek with local guide and trail snacks.', '1899.00', 'Nature', 30],
            ['Food Crawl Experience', 'Taste top local dishes with a culinary guide.', '1299.00', 'Food', 50],
            ['Waterfall Retreat', 'Private transport to hidden waterfalls and picnic lunch.', '2799.00', 'Nature', 18],
            ['Beach Camping Weekend', 'Overnight beach camp with equipment included.', '3599.00', 'Adventure', 10],
            ['Cultural Dance Night', 'Traditional performance with buffet dinner.', '999.00', 'Culture', 60],
            ['Scuba Discovery Session', 'Introductory scuba session with instructor.', '5499.00', 'Adventure', 8],
            ['Wellness Spa Escape', 'Day pass with massage and wellness amenities.', '3999.00', 'Wellness', 15],
        ];

        $services = [];
        foreach ($serviceSeed as $index => $seed) {
            [$title, $description, $price, $category, $slots] = $seed;

            $service = new Service();
            $service->setTitle($title);
            $service->setDescription($description);
            $service->setPrice($price);
            $service->setCategory($category);
            $service->setSlots($slots);
            $service->setOwner($index % 2 === 0 ? $admin : $staff);

            $manager->persist($service);
            $services[] = $service;

            $inventory = new Inventory();
            $inventory->setService($service);
            $inventory->setQuantityAvailable($slots);
            $inventory->setLastUpdated(new \DateTime());
            $manager->persist($inventory);
        }

        $bookingSeeds = [
            ['Confirmed', 2, 0, $admin],
            ['Pending', 1, 1, $staff],
            ['Cancelled', 3, 2, $admin],
            ['Confirmed', 4, 3, $staff],
            ['Pending', 2, 4, $admin],
            ['Confirmed', 1, 5, $staff],
        ];

        $bookings = [];
        foreach ($bookingSeeds as $seed) {
            [$status, $quantity, $serviceIndex, $user] = $seed;
            $service = $services[$serviceIndex] ?? null;
            if (!$service) {
                continue;
            }

            $booking = new Booking();
            $booking->setUser($user);
            $booking->setService($service);
            $booking->setQuantity($quantity);
            $booking->setStatus($status);
            $booking->setBookingDate(new \DateTime());

            $price = (float) $service->getPrice();
            $total = $price * $quantity;
            $booking->setTotalAmount(sprintf('%.2f', $total));

            $manager->persist($booking);
            $bookings[] = $booking;
        }

        $paymentSeeds = [
            ['Card', 'Confirmed', $admin],
            ['Cash', 'Pending', $staff],
            ['GCash', 'Confirmed', $admin],
            ['Bank Transfer', 'Confirmed', $staff],
        ];

        foreach ($paymentSeeds as $index => $seed) {
            [$method, $status, $owner] = $seed;
            $booking = $bookings[$index] ?? null;
            if (!$booking) {
                continue;
            }

            $payment = new Payment();
            $payment->setOwner($owner);
            $payment->setBooking($booking);
            $payment->setAmount($booking->getTotalAmount() ?? '0.00');
            $payment->setMethod($method);
            $payment->setPaymentStatus($status);
            $payment->setPaymentDate(new \DateTime());
            $manager->persist($payment);
        }

        $budgetSeeds = [
            [$admin, 'Marketing', '15000.00', '8200.00', 'Jan 2026 - Mar 2026'],
            [$admin, 'Operations', '20000.00', '15450.00', 'Jan 2026 - Mar 2026'],
            [$staff, 'Supplies', '8000.00', '4100.00', 'Jan 2026 - Mar 2026'],
            [$staff, 'Training', '6000.00', '2400.00', 'Jan 2026 - Mar 2026'],
        ];

        foreach ($budgetSeeds as $seed) {
            [$user, $category, $planned, $spent, $range] = $seed;

            $budget = new BudgetTracker();
            $budget->setUser($user);
            $budget->setCategory($category);
            $budget->setAmountPlanned($planned);
            $budget->setAmountSpent($spent);
            $budget->setDateRange($range);
            $manager->persist($budget);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
