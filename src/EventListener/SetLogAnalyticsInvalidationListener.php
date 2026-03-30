<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\SetLog;
use App\Service\Analytics\AnalyticsModule;
use App\Service\Analytics\AnalyticsSnapshotService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
class SetLogAnalyticsInvalidationListener
{
    public function __construct(
        private readonly AnalyticsSnapshotService $analyticsSnapshotService,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof SetLog) {
            return;
        }

        $user = $entity->getWorkoutLog()->getAthlete();

        // DQL DELETE inside invalidate() is safe in postPersist — it bypasses UnitOfWork
        // and does NOT trigger further lifecycle events, so no infinite loop risk.
        $this->analyticsSnapshotService->invalidate($user, [
            AnalyticsModule::FATIGUE_DETECTION,
            AnalyticsModule::TRAINING_VOLUME,
        ]);
    }
}
