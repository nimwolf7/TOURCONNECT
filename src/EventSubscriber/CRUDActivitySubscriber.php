<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\Booking;
use App\Entity\BudgetTracker;
use App\Entity\Inventory;
use App\Entity\Payment;
use App\Entity\Service;
use App\Entity\User;
use App\Service\AuditLogger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class CRUDActivitySubscriber
{
    public function __construct(
        private Security $security,
        private AuditLogger $auditLogger
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $user = $this->security->getUser();

        if (!$user instanceof User || $entity instanceof ActivityLog) {
            return;
        }

        $action = $this->getActionMessage('Created', $entity);
        if ($action) {
            $this->auditLogger->log($user, $action);
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $user = $this->security->getUser();

        if (!$user instanceof User || $entity instanceof ActivityLog) {
            return;
        }

        $action = $this->getActionMessage('Updated', $entity);
        if ($action) {
            $this->auditLogger->log($user, $action);
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        $user = $this->security->getUser();

        if (!$user instanceof User || $entity instanceof ActivityLog) {
            return;
        }

        $action = $this->getActionMessage('Deleted', $entity);
        if ($action) {
            $this->auditLogger->log($user, $action);
        }
    }

    private function getActionMessage(string $action, object $entity): ?string
    {
        $entityName = match (true) {
            $entity instanceof User => 'User',
            $entity instanceof Service => 'Service',
            $entity instanceof Booking => 'Booking',
            $entity instanceof Payment => 'Payment',
            $entity instanceof Inventory => 'Inventory',
            $entity instanceof BudgetTracker => 'Budget Tracker',
            default => null,
        };

        if (!$entityName) {
            return null;
        }

        $identifier = method_exists($entity, 'getId') ? '#' . $entity->getId() : '';
        
        if ($entity instanceof User && method_exists($entity, 'getUsername')) {
            $identifier = $entity->getUsername();
        } elseif ($entity instanceof Service && method_exists($entity, 'getTitle')) {
            $identifier = $entity->getTitle();
        }

        return sprintf('%s %s %s', $action, $entityName, $identifier);
    }
}
