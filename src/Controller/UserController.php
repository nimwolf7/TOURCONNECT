<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($request->isMethod('POST')) {
            $user = new User();
            $user->setUsername($request->request->get('username'));
            
            // Hash the password
            $plainPassword = $request->request->get('password');
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            
            // Set roles
            $roles = $request->request->all('roles') ?? [];
            $user->setRoles($roles);

            $entityManager->persist($user);
            $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Created user #' . $user->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            $this->addFlash('success', 'User created successfully!');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/new.html.twig');
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($request->isMethod('POST')) {
            $user->setUsername($request->request->get('username'));
            
            // Update password if provided
            $plainPassword = $request->request->get('password');
            if (!empty($plainPassword)) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }
            
            // Update roles
            $roles = $request->request->all('roles') ?? [];
            $user->setRoles($roles);

            $entityManager->flush();

            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Edited user #' . $user->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();

            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            // Log activity
            $activityLog = new \App\Entity\ActivityLog();
            $activityLog->setUser($this->getUser());
            $activityLog->setAction('Deleted user #' . $user->getId());
            $activityLog->setTimestamp(new \DateTime());
            $activityLog->setIpAddress($request->getClientIp() ?? 'unknown');
            $entityManager->persist($activityLog);
            $entityManager->flush();
            $this->addFlash('success', 'User deleted successfully!');
        }

        return $this->redirectToRoute('app_user_index');
    }
}
