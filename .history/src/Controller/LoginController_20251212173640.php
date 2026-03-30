<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route(path: '/', name: 'app_home')]
    public function home(): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            } elseif ($this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('app_service_index');
            }
        }
        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            } elseif ($this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('app_service_index');
            }
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        // Disable public registration - only admin and staff accounts
        $this->addFlash('warning', 'Registration is disabled. Please contact an administrator for account access.');
        return $this->redirectToRoute('app_login');
        
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $username = trim($request->request->get('username'));
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            $errors = [];
            
            if (empty($username) || strlen($username) < 3) {
                $errors[] = 'Username must be at least 3 characters long';
            }
            
            if (empty($password) || strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters long';
            }
            
            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match';
            }
            
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
            if ($existingUser) {
                $errors[] = 'Username already exists';
            }

            if (empty($errors)) {
                $user = new User();
                $user->setUsername($username);
                $user->setRoles(['ROLE_USER']);
                
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', '✅ Registration successful! Please log in.');
                return $this->redirectToRoute('app_login');
            }

            return $this->render('security/register.html.twig', [
                'errors' => $errors,
                'username' => $username,
            ]);
        }

        return $this->render('security/register.html.twig', [
            'errors' => [],
            'username' => '',
        ]);
    }
}
