<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {
    }

    public function log(User $user, string $action): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request ? $request->getClientIp() : '127.0.0.1';

        $activityLog = new ActivityLog();
        $activityLog->setUser($user);
        $activityLog->setAction($action);
        $activityLog->setTimestamp(new \DateTime());
        $activityLog->setIpAddress($ipAddress);

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();
    }
}
