<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payment')]
class PaymentController extends AbstractController
{
    #[Route('/', name: 'app_payment_index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository): Response
    {
        $payments = $paymentRepository->findAll();

        // Compute total amount
        $totalAmount = array_reduce($payments, fn($sum, $p) => $sum + $p->getAmount(), 0);

        return $this->render('payment/index.html.twig', [
            'payments' => $payments,
            'totalAmount' => $totalAmount,
        ]);
    }

    #[Route('/new', name: 'app_payment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
              $payment->setOwner($this->getUser());
              $entityManager->persist($payment);
              $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Created payment #' . $payment->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            $this->addFlash('success', 'Payment added successfully!');
            return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_payment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_payment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        // Staff can now edit any payment, including those created by admin
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Edited payment #' . $payment->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            $this->addFlash('success', 'Payment updated successfully!');
            return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/edit.html.twig', [
            'payment' => $payment,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_payment_delete', methods: ['POST'])]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        // Restrict staff to only delete their own payments
        $user = $this->getUser();
        $booking = $payment->getBooking();
        if (in_array('ROLE_STAFF', $user->getRoles(), true)) {
            if (!$booking || $booking->getUser() !== $user) {
                throw $this->createAccessDeniedException('You can only delete your own payments.');
            }
        }
        if ($this->isCsrfTokenValid('delete' . $payment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($payment);
            $entityManager->flush();
            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Deleted payment #' . $payment->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            $this->addFlash('danger', 'Payment deleted successfully!');
        }

        return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
    }
}