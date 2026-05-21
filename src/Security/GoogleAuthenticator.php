<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);
        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);
        $email = $googleUser->getEmail();

        if (!$email) {
            throw new AuthenticationException('Google account did not return an email address.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($email): UserInterface {

                $user = $this->userRepository->findOneBy(['username' => $email]);
                if (!$user) {
                    $user = $this->userRepository->findOneBy(['email' => $email]);
                }
                if ($user) {
                    if (!$user->getEmail()) {
                        $user->setEmail($email);
                    }
                    if (!$user->isVerified()) {
                        $user->setIsVerified(true);
                        $user->setVerifiedAt(new \DateTimeImmutable());
                        $user->setVerificationToken(null);
                    }
                    $this->entityManager->flush();
                    return $user;
                }

                $user = new User();
                $user->setUsername($email);
                $user->setEmail($email);
                $user->setRoles(['ROLE_USER']);
                $user->setIsVerified(true);
                $user->setVerifiedAt(new \DateTimeImmutable());

                $randomPassword = bin2hex(random_bytes(20));
                $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        // Consume any stale danger flash so it does not show after Google login.
        $request->getSession()->getFlashBag()->get('danger');
        $request->getSession()->getFlashBag()->add('expand_sidebar_once', '1');
        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        $request->getSession()->getFlashBag()->add('danger', 'Google login failed. Please try again.');
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
