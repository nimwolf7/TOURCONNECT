<?php

namespace App\Controller;

use App\Entity\BudgetTracker;
use App\Entity\User;
use App\Form\BudgetTrackerType;
use App\Repository\BookingRepository;
use App\Repository\BudgetTrackerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/budget/tracker')]
final class BudgetTrackerController extends AbstractController
{
    #[Route(name: 'app_budget_tracker_index', methods: ['GET'])]
    public function index(BudgetTrackerRepository $budgetTrackerRepository): Response
    {
        return $this->render('budget_tracker/index.html.twig', [
            'budget_trackers' => $budgetTrackerRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_budget_tracker_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, BookingRepository $bookingRepository): Response
    {
        $budgetTracker = new BudgetTracker();
        $form = $this->createForm(BudgetTrackerType::class, $budgetTracker);
        $form->handleRequest($request);
        $bookingAmountMap = $this->buildBookingAmountMap($bookingRepository);

        if ($form->isSubmitted() && $form->isValid()) {
            $booking = $budgetTracker->getBooking();
            $bookingUser = $budgetTracker->getBooking()?->getUser();
            if (!$bookingUser instanceof User) {
                $this->addFlash('error', 'Selected booking has no customer/user assigned.');

                return $this->render('budget_tracker/new.html.twig', [
                    'budget_tracker' => $budgetTracker,
                    'form' => $form,
                    'booking_amount_map' => $bookingAmountMap,
                ]);
            }

            $budgetTracker->setUser($bookingUser);
            if ($booking !== null) {
                $budgetTracker->setAmountSpent((string) $booking->getTotalAmount());
            }
            $entityManager->persist($budgetTracker);
            $entityManager->flush();

            return $this->redirectToRoute('app_budget_tracker_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('budget_tracker/new.html.twig', [
            'budget_tracker' => $budgetTracker,
            'form' => $form,
            'booking_amount_map' => $bookingAmountMap,
        ]);
    }

    #[Route('/{id}', name: 'app_budget_tracker_show', methods: ['GET'])]
    public function show(BudgetTracker $budgetTracker): Response
    {
        return $this->render('budget_tracker/show.html.twig', [
            'budget_tracker' => $budgetTracker,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_budget_tracker_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BudgetTracker $budgetTracker, EntityManagerInterface $entityManager, BookingRepository $bookingRepository): Response
    {
        // Staff can now edit any budget tracker record, including those created by admin
        $form = $this->createForm(BudgetTrackerType::class, $budgetTracker);
        $form->handleRequest($request);
        $bookingAmountMap = $this->buildBookingAmountMap($bookingRepository);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $booking = $budgetTracker->getBooking();
            $bookingUser = $budgetTracker->getBooking()?->getUser();
            if (!$bookingUser instanceof User) {
                $this->addFlash('error', 'Selected booking has no customer/user assigned.');

                return $this->render('budget_tracker/edit.html.twig', [
                    'budget_tracker' => $budgetTracker,
                    'form' => $form,
                    'booking_amount_map' => $bookingAmountMap,
                ]);
            }

            $budgetTracker->setUser($bookingUser);
            if ($booking !== null) {
                $budgetTracker->setAmountSpent((string) $booking->getTotalAmount());
            }
            $entityManager->flush();

            return $this->redirectToRoute('app_budget_tracker_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('budget_tracker/edit.html.twig', [
            'budget_tracker' => $budgetTracker,
            'form' => $form,
            'booking_amount_map' => $bookingAmountMap,
        ]);
    }

    #[Route('/{id}', name: 'app_budget_tracker_delete', methods: ['POST'])]
    public function delete(Request $request, BudgetTracker $budgetTracker, EntityManagerInterface $entityManager): Response
    {
        // Restrict staff to only delete their own budget tracker records
        $user = $this->getUser();
        if (in_array('ROLE_STAFF', $user->getRoles(), true)) {
            if ($budgetTracker->getUser() !== $user) {
                throw $this->createAccessDeniedException('You can only delete your own budget tracker records.');
            }
        }
        if ($this->isCsrfTokenValid('delete'.$budgetTracker->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($budgetTracker);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_budget_tracker_index', [], Response::HTTP_SEE_OTHER);
    }

    private function buildBookingAmountMap(BookingRepository $bookingRepository): array
    {
        $map = [];
        foreach ($bookingRepository->findAll() as $booking) {
            $bookingId = $booking->getId();
            if ($bookingId !== null) {
                $map[(string) $bookingId] = (string) $booking->getTotalAmount();
            }
        }

        return $map;
    }
}
