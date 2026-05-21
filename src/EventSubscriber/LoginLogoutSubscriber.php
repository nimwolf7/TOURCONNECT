<?php

namespace App\EventSubscriber;

use App\Service\AuditLogger;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LoginLogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLogger $auditLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof User) {
            $isCustomerOnly = !in_array('ROLE_ADMIN', $user->getRoles(), true)
                && !in_array('ROLE_STAFF', $user->getRoles(), true);
            $this->auditLogger->log($user, $isCustomerOnly ? 'Customer Login' : 'User Login');
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();

        if ($user instanceof User) {
            $isCustomerOnly = !in_array('ROLE_ADMIN', $user->getRoles(), true)
                && !in_array('ROLE_STAFF', $user->getRoles(), true);
            $this->auditLogger->log($user, $isCustomerOnly ? 'Customer Logout' : 'User Logout');
        }
    }
}
