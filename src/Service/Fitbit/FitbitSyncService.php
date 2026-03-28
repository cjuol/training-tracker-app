<?php

declare(strict_types=1);

namespace App\Service\Fitbit;

use App\Entity\DailyHeartRate;
use App\Entity\DailySteps;
use App\Entity\FitbitActivity;
use App\Entity\FitbitToken;
use App\Entity\SleepLog;
use App\Entity\User;
use App\Repository\DailyHeartRateRepository;
use App\Repository\DailyStepsRepository;
use App\Repository\FitbitActivityRepository;
use App\Repository\SleepLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class FitbitSyncService
{
    public function __construct(
        private readonly FitbitApiClient $apiClient,
        private readonly EntityManagerInterface $em,
        private readonly DailyStepsRepository $stepsRepo,
        private readonly DailyHeartRateRepository $heartRateRepo,
        private readonly FitbitActivityRepository $activityRepo,
        private readonly SleepLogRepository $sleepRepo,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function syncUser(User $user, \DateTimeInterface $date): void
    {
        $token = $user->getFitbitToken();
        if (!$token || !$token->isValid()) {
            return;
        }
        $this->syncSteps($user, $token, $date);
        $this->syncHeartRate($user, $token, $date);
        $this->syncSleep($user, $token, $date);
        $this->syncActivities($user, $token, $date);
        $yesterday = \DateTimeImmutable::createFromInterface($date)->modify('-1 day');
        $this->syncIntradayHeartRate($user, $token, $date);
        $this->syncIntradayHeartRate($user, $token, $yesterday);
        $this->em->flush();
    }

    private function syncSteps(User $user, FitbitToken $token, \DateTimeInterface $date): void
    {
        $dateStr = $date->format('Y-m-d');
        $data = $this->apiClient->get($token, "/1/user/-/activities/steps/date/{$dateStr}/1d.json");
        $steps = (int) ($data['activities-steps'][0]['value'] ?? 0);
        if ($steps === 0) {
            return;
        }

        $record = $this->stepsRepo->findOneBy(['user' => $user, 'date' => new \DateTimeImmutable($dateStr)]);
        if (!$record) {
            $record = new DailySteps();
            $record->setUser($user);
            $record->setDate(new \DateTimeImmutable($dateStr));
            $this->em->persist($record);
        }
        $record->setSteps($steps);
        $record->setSource('fitbit');
    }

    private function syncHeartRate(User $user, FitbitToken $token, \DateTimeInterface $date): void
    {
        $dateStr = $date->format('Y-m-d');
        $data = $this->apiClient->get($token, "/1/user/-/activities/heart/date/{$dateStr}/1d.json");
        $hrData = $data['activities-heart'][0]['value'] ?? [];

        $restingHr = isset($hrData['restingHeartRate']) ? (int) $hrData['restingHeartRate'] : null;
        $zones = $hrData['heartRateZones'] ?? [];
        $calories = isset($hrData['customHeartRateZones']) ? null : ((int) ($zones[0]['caloriesOut'] ?? 0) ?: null);

        $record = $this->heartRateRepo->findByUserAndDate($user, new \DateTimeImmutable($dateStr));
        if (!$record) {
            $record = new DailyHeartRate();
            $record->setUser($user);
            $record->setDate(new \DateTimeImmutable($dateStr));
            $this->em->persist($record);
        }
        $record->setRestingHeartRate($restingHr);
        $record->setZones($zones ?: null);
        $record->setCaloriesOut($calories);
    }

    private function syncIntradayHeartRate(User $user, FitbitToken $token, \DateTimeInterface $date): void
    {
        $dateStr = $date->format('Y-m-d');
        try {
            $data = $this->apiClient->get($token, "/1/user/-/activities/heart/date/{$dateStr}/1d/1min/time/00:00/23:59.json");
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'HTTP 403')) {
                $this->logger->warning('Fitbit intraday HR: 403 Forbidden (non-Personal app?), skipping.', ['date' => $dateStr]);

                return;
            }
            throw $e;
        }

        $dataset = $data['activities-heart-intraday']['dataset'] ?? [];

        if (empty($dataset)) {
            return;
        }

        $record = $this->heartRateRepo->findByUserAndDate($user, new \DateTimeImmutable($dateStr));
        if (!$record) {
            $record = new DailyHeartRate();
            $record->setUser($user);
            $record->setDate(new \DateTimeImmutable($dateStr));
            $this->em->persist($record);
        }
        $record->setIntradayData($dataset);
    }

    private function syncSleep(User $user, FitbitToken $token, \DateTimeInterface $date): void
    {
        $dateStr = $date->format('Y-m-d');
        $data = $this->apiClient->get($token, "/1/user/-/sleep/date/{$dateStr}.json");
        $sleepData = $data['sleep'][0] ?? null;
        if (!$sleepData || !($sleepData['isMainSleep'] ?? false)) {
            return;
        }

        $record = $this->sleepRepo->findByUserAndDate($user, new \DateTimeImmutable($dateStr));
        if (!$record) {
            $record = new SleepLog();
            $record->setUser($user);
            $record->setDate(new \DateTimeImmutable($dateStr));
            $this->em->persist($record);
        }
        $record->setDurationMinutes((int) round(($sleepData['duration'] ?? 0) / 60000));
        $record->setEfficiency(isset($sleepData['efficiency']) ? (int) $sleepData['efficiency'] : null);
        $record->setStartTime(isset($sleepData['startTime']) ? new \DateTimeImmutable($sleepData['startTime']) : null);
        $record->setEndTime(isset($sleepData['endTime']) ? new \DateTimeImmutable($sleepData['endTime']) : null);
        $record->setStages($sleepData['levels']['summary'] ?? null);
    }

    private function syncActivities(User $user, FitbitToken $token, \DateTimeInterface $date): void
    {
        $dateStr = $date->format('Y-m-d');
        $data = $this->apiClient->get($token, "/1/user/-/activities/date/{$dateStr}.json");
        foreach ($data['activities'] ?? [] as $act) {
            $fitbitLogId = (string) $act['logId'];
            $record = $this->activityRepo->findByUserAndFitbitLogId($user, $fitbitLogId);
            if (!$record) {
                $record = new FitbitActivity();
                $record->setUser($user);
                $record->setFitbitLogId($fitbitLogId);
                $record->setDate(new \DateTimeImmutable($dateStr));
                $this->em->persist($record);
            }
            $record->setName($act['activityName'] ?? 'Unknown');
            $record->setDurationMinutes((int) round(($act['duration'] ?? 0) / 60000));
            $record->setCalories(isset($act['calories']) ? (int) $act['calories'] : null);
            $record->setSteps(isset($act['steps']) ? (int) $act['steps'] : null);
            $record->setDistance(isset($act['distance']) ? (float) $act['distance'] : null);
            $record->setAverageHeartRate(isset($act['averageHeartRate']) ? (int) $act['averageHeartRate'] : null);
        }
    }
}
