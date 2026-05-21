<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Form\ActivityLogType;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activity/log')]
#[IsGranted('ROLE_ADMIN')]
final class ActivityLogController extends AbstractController
{
    #[Route(name: 'app_activity_log_index', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $filter = (string) $request->query->get('filter', 'all');
        $range = (string) $request->query->get('range', 'all');

        $keywords = match ($filter) {
            'auth' => ['Login', 'Logout'],
            'customer_auth' => ['Customer Login', 'Customer Logout'],
            default => [],
        };

        $fromDate = match ($range) {
            'today' => new \DateTime('today'),
            '7d' => new \DateTime('-7 days'),
            '30d' => new \DateTime('-30 days'),
            default => null,
        };

        $activityLogs = $activityLogRepository->findFiltered($keywords, $fromDate);

        return $this->render('activity_log/index.html.twig', [
            'activity_logs' => $activityLogs,
            'selected_filter' => $filter,
            'selected_range' => $range,
        ]);
    }

    #[Route('/new', name: 'app_activity_log_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $activityLog = new ActivityLog();
        $form = $this->createForm(ActivityLogType::class, $activityLog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($activityLog);
            $entityManager->flush();

            return $this->redirectToRoute('app_activity_log_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('activity_log/new.html.twig', [
            'activity_log' => $activityLog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_activity_log_show', methods: ['GET'])]
    public function show(ActivityLog $activityLog): Response
    {
        return $this->render('activity_log/show.html.twig', [
            'activity_log' => $activityLog,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_activity_log_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ActivityLog $activityLog, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ActivityLogType::class, $activityLog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_activity_log_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('activity_log/edit.html.twig', [
            'activity_log' => $activityLog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_activity_log_delete', methods: ['POST'])]
    public function delete(Request $request, ActivityLog $activityLog, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$activityLog->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($activityLog);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_activity_log_index', [], Response::HTTP_SEE_OTHER);
    }
}
