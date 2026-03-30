<?php

namespace App\Controller;

use App\Entity\BudgetTracker;
use App\Form\BudgetTrackerType;
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
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $budgetTracker = new BudgetTracker();
        $form = $this->createForm(BudgetTrackerType::class, $budgetTracker);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($budgetTracker);
            $entityManager->flush();

            return $this->redirectToRoute('app_budget_tracker_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('budget_tracker/new.html.twig', [
            'budget_tracker' => $budgetTracker,
            'form' => $form,
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
    public function edit(Request $request, BudgetTracker $budgetTracker, EntityManagerInterface $entityManager): Response
    {
        // Staff can now edit any budget tracker record, including those created by admin
        $form = $this->createForm(BudgetTrackerType::class, $budgetTracker);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_budget_tracker_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('budget_tracker/edit.html.twig', [
            'budget_tracker' => $budgetTracker,
            'form' => $form,
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
}
