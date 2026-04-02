<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\BodyMeasurementRepository;
use App\Repository\DailyHeartRateRepository;
use App\Repository\DailyStepsRepository;
use App\Repository\DailyWellnessMetricsRepository;
use App\Repository\SleepLogRepository;
use App\Repository\WorkoutLogRepository;
use App\Service\Analytics\AnalyticsSnapshotService;
use App\Service\CoachDashboardService;
use Cjuol\StatGuard\RobustStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(
        AssignedMesocycleRepository $assignedRepo,
        WorkoutLogRepository $workoutLogRepo,
        AnalyticsSnapshotService $analyticsSnapshotService,
        CoachDashboardService $dashboardService,
        SleepLogRepository $sleepLogRepo,
        DailyHeartRateRepository $heartRateRepo,
        DailyStepsRepository $stepsRepo,
        DailyWellnessMetricsRepository $wellnessRepo,
        BodyMeasurementRepository $bodyMeasurementRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $isAthlete = $this->isGranted('ROLE_ATLETA');
        $isCoach   = $this->isGranted('ROLE_ENTRENADOR');

        // ---- Athlete data ----
        $athleteData = [];
        if ($isAthlete) {
            $today     = new \DateTimeImmutable('today');
            $yesterday = $today->modify('-1 day');
            $todayEnd  = $today->modify('tomorrow - 1 second');
            $todayDow  = (int) (new \DateTimeImmutable())->format('N'); // 1=Mon … 7=Sun

            // ----------------------------------------------------------------
            // 1. Greeting
            // ----------------------------------------------------------------
            $hour = (int) (new \DateTimeImmutable())->format('G');
            if ($hour >= 6 && $hour < 12) {
                $greeting = 'Buenos días';
            } elseif ($hour >= 12 && $hour < 20) {
                $greeting = 'Buenas tardes';
            } else {
                $greeting = 'Buenas noches';
            }

            $spanishMonths = [
                1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
            ];
            $spanishWeekdays = [
                1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
                5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
            ];
            $now = new \DateTimeImmutable();
            $todayFormatted = sprintf(
                '%s %d de %s',
                $spanishWeekdays[(int) $now->format('N')],
                (int) $now->format('j'),
                $spanishMonths[(int) $now->format('n')]
            );

            // ----------------------------------------------------------------
            // 2. Active mesocycle
            // ----------------------------------------------------------------
            $activeAssignments = $assignedRepo->findActiveByAthlete($user);
            $firstAssignment   = $activeAssignments[0] ?? null;
            $activeMesocycle   = $firstAssignment?->getMesocycle();

            // Compute week/day context
            $mesocycleWeek = null;
            $mesocycleDay  = null;
            if ($firstAssignment !== null) {
                $startDate   = $firstAssignment->getStartDate();
                $diffDays    = (int) $startDate->diff($today)->days;
                $mesocycleDay  = ($diffDays % 7) + 1;
                $mesocycleWeek = (int) ($diffDays / 7) + 1;
            }

            // ----------------------------------------------------------------
            // 3. Today's sessions
            // ----------------------------------------------------------------
            $allSessions  = $activeMesocycle?->getWorkoutSessions()->toArray() ?? [];
            // Sort by orderIndex
            usort($allSessions, static fn ($a, $b): int => $a->getOrderIndex() <=> $b->getOrderIndex());

            $todaySessions = array_values(array_filter(
                $allSessions,
                static fn ($s): bool => $s->getDayOfWeek() === $todayDow
            ));

            if (empty($todaySessions) && !empty($allSessions)) {
                $todaySessions = $allSessions; // fallback: show all
            }

            // ----------------------------------------------------------------
            // 4. In-progress + completed today
            // ----------------------------------------------------------------
            $inProgressMap = $workoutLogRepo->findInProgressByAthleteIndexedBySession($user);
            $sessionInProgress = [];
            foreach ($inProgressMap as $sessionId => $log) {
                $sessionInProgress[$sessionId] = $log->getId();
            }

            $completedToday      = $workoutLogRepo->findCompletedByAthleteInDateRange($user, $today, $todayEnd);
            $completedSessionIds = array_map(
                static fn ($wl): int => $wl->getWorkoutSession()->getId(),
                $completedToday
            );

            // ----------------------------------------------------------------
            // 5. 7-day weekly calendar (Mon=1 … Sun=7)
            // ----------------------------------------------------------------
            $monday = $today->modify('monday this week');
            $sunday = $today->modify('sunday this week')->setTime(23, 59, 59);
            $completedThisWeek = $workoutLogRepo->findCompletedByAthleteInDateRange($user, $monday, $sunday);

            // Map DOW → session letter(s) from allSessions
            $sessionLetterMap = []; // DOW → 'A', 'B', etc.
            foreach ($allSessions as $idx => $session) {
                $dow = $session->getDayOfWeek();
                if ($dow !== null) {
                    $sessionLetterMap[$dow] = chr(65 + $idx); // A, B, C…
                }
            }

            // Map DOW → completed workoutLog IDs this week
            $completedDows = [];
            foreach ($completedThisWeek as $wl) {
                $dow = (int) $wl->getStartTime()->format('N');
                $completedDows[$dow] = true;
            }

            $weekCalendar = [];
            $dayAbbreviations = [
                1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue',
                5 => 'Vie', 6 => 'Sáb', 7 => 'Dom',
            ];
            for ($dow = 1; $dow <= 7; $dow++) {
                $weekCalendar[$dow] = [
                    'abbr'      => $dayAbbreviations[$dow],
                    'letter'    => $sessionLetterMap[$dow] ?? null,
                    'completed' => isset($completedDows[$dow]),
                    'isToday'   => $dow === $todayDow,
                    'isRest'    => !isset($sessionLetterMap[$dow]),
                ];
            }

            // ----------------------------------------------------------------
            // 6. Analytics snapshot (cached, no recompute)
            // ----------------------------------------------------------------
            $cachedVerdictMap  = $analyticsSnapshotService->getCachedForUsers([$user]);
            $analyticsVerdicts = $cachedVerdictMap[$user->getId()] ?? [];

            $sleepVerdict    = $analyticsVerdicts['sleep_scoring'] ?? null;
            $recoveryVerdict = $analyticsVerdicts['recovery_index'] ?? null;
            $fatigueVerdict  = $analyticsVerdicts['fatigue_detection'] ?? null;

            $sleepScore    = $sleepVerdict    !== null ? (int) round($sleepVerdict->score)    : -1;
            $recoveryScore = $recoveryVerdict !== null ? (int) round($recoveryVerdict->score) : -1;
            $fatigueScore  = $fatigueVerdict  !== null ? (int) round($fatigueVerdict->score)  : -1;

            // ----------------------------------------------------------------
            // 7. Vitals: RHR + caloriesOut (today → yesterday fallback)
            // ----------------------------------------------------------------
            $heartRateRecord = $heartRateRepo->findByUserAndDate($user, $today)
                ?? $heartRateRepo->findByUserAndDate($user, $yesterday);

            $currentRhr    = $heartRateRecord?->getRestingHeartRate();
            $caloriesOut   = $heartRateRecord?->getCaloriesOut();

            // 14-day Huber mean baseline for RHR
            $rhrRecords = $heartRateRepo->findRecentByUser($user, 14);
            $rhrValues  = [];
            foreach ($rhrRecords as $record) {
                $rhr = $record->getRestingHeartRate();
                if ($rhr !== null) {
                    $rhrValues[] = (float) $rhr;
                }
            }
            $rhrBaseline = null;
            if (count($rhrValues) >= 2) {
                $rs          = new RobustStats();
                $rhrBaseline = (int) round($rs->getHuberMean($rhrValues));
            }

            // ----------------------------------------------------------------
            // 8. Latest wellness metrics (RMSSD)
            // ----------------------------------------------------------------
            $wellness = $wellnessRepo->findLatestByUser($user);
            $rmssd    = $wellness?->getRmssd();

            // ----------------------------------------------------------------
            // 9. Yesterday's sleep
            // ----------------------------------------------------------------
            $sleepLog = $sleepLogRepo->findByUserAndDate($user, $yesterday);
            $sleepDurationMinutes = $sleepLog?->getDurationMinutes() ?? 0;

            // Extract deep/REM stage minutes from stages JSON
            $sleepDeepMinutes = 0;
            $sleepRemMinutes  = 0;
            if ($sleepLog !== null && $sleepLog->getStages() !== null) {
                foreach ($sleepLog->getStages() as $stage) {
                    $stageName = strtolower((string) ($stage['type'] ?? $stage['level'] ?? ''));
                    $stageMin  = (int) ($stage['minutes'] ?? 0);
                    if (str_contains($stageName, 'deep')) {
                        $sleepDeepMinutes += $stageMin;
                    } elseif (str_contains($stageName, 'rem')) {
                        $sleepRemMinutes += $stageMin;
                    }
                }
            }

            // ----------------------------------------------------------------
            // 10. Body measurements for 4-week weight trend
            // ----------------------------------------------------------------
            $latestMeasurement = $bodyMeasurementRepo->findLatestByAthlete($user);
            $oldMeasurements   = $bodyMeasurementRepo->findByAthleteAndDateRange(
                $user,
                $today->modify('-4 weeks'),
                $today->modify('-3 weeks 6 days')
            );
            $oldMeasurement = $oldMeasurements[0] ?? null;

            $weightTrend = '→';
            if ($latestMeasurement?->getWeightKg() !== null && $oldMeasurement?->getWeightKg() !== null) {
                $diff = $latestMeasurement->getWeightKg() - $oldMeasurement->getWeightKg();
                if ($diff > 0.1) {
                    $weightTrend = '↑';
                } elseif ($diff < -0.1) {
                    $weightTrend = '↓';
                }
            }

            // ----------------------------------------------------------------
            // 11. Today's steps + goal
            // ----------------------------------------------------------------
            $stepsRecord  = $stepsRepo->findByUserAndDate($user, $today);
            $stepsToday   = $stepsRecord?->getSteps() ?? 0;
            $stepsTarget  = $activeMesocycle?->getDailyStepsTarget() ?? 10_000;

            // ----------------------------------------------------------------
            // 12. Last completed workout
            // ----------------------------------------------------------------
            $lastWorkout = $workoutLogRepo->findLastByAthlete($user);
            $lastWorkoutVerdict = $analyticsVerdicts['training_volume'] ?? null;

            // Compute last workout stats from setLogs (if available)
            $lastWorkoutStats = null;
            if ($lastWorkout !== null) {
                $setLogs    = $lastWorkout->getSetLogs();
                $setCount   = $setLogs->count();
                $exerciseIds = [];
                $tonnage    = 0.0;
                foreach ($setLogs as $setLog) {
                    $exerciseIds[$setLog->getSessionExercise()->getId()] = true;
                    if ($setLog->getWeight() !== null && $setLog->getReps() !== null) {
                        $tonnage += $setLog->getWeight() * $setLog->getReps();
                    }
                }
                $exerciseCount = count($exerciseIds);

                $durationSeconds = null;
                if ($lastWorkout->getEndTime() !== null) {
                    $durationSeconds = $lastWorkout->getEndTime()->getTimestamp()
                        - $lastWorkout->getStartTime()->getTimestamp();
                }

                $lastWorkoutStats = [
                    'setCount'        => $setCount,
                    'exerciseCount'   => $exerciseCount,
                    'tonnage'         => $tonnage,
                    'durationSeconds' => $durationSeconds,
                ];
            }

            // ----------------------------------------------------------------
            // Assemble athleteData
            // ----------------------------------------------------------------
            $athleteData = [
                // Greeting
                'greeting'        => $greeting,
                'todayFormatted'  => $todayFormatted,
                // Mesocycle context
                'activeMesocycle'  => $activeMesocycle,
                'firstAssignment'  => $firstAssignment,
                'mesocycleWeek'    => $mesocycleWeek,
                'mesocycleDay'     => $mesocycleDay,
                // Sessions
                'todaySessions'        => $todaySessions,
                'allSessions'          => $allSessions,
                'sessionInProgress'    => $sessionInProgress,
                'completedSessionIds'  => $completedSessionIds,
                // Week calendar
                'weekCalendar'    => $weekCalendar,
                // Scores
                'sleepScore'      => $sleepScore,
                'recoveryScore'   => $recoveryScore,
                'fatigueScore'    => $fatigueScore,
                'fatigueVerdict'  => $fatigueVerdict,
                // Vitals
                'currentRhr'      => $currentRhr,
                'rhrBaseline'     => $rhrBaseline,
                'caloriesOut'     => $caloriesOut,
                'rmssd'           => $rmssd,
                // Sleep
                'sleepDurationMinutes' => $sleepDurationMinutes,
                'sleepDeepMinutes'     => $sleepDeepMinutes,
                'sleepRemMinutes'      => $sleepRemMinutes,
                // Weight
                'latestMeasurement' => $latestMeasurement,
                'weightTrend'       => $weightTrend,
                // Goals
                'stepsToday'       => $stepsToday,
                'stepsTarget'      => $stepsTarget,
                // Last workout
                'lastWorkout'      => $lastWorkout,
                'lastWorkoutStats' => $lastWorkoutStats,
                'lastWorkoutVerdict' => $lastWorkoutVerdict,
                // Backward-compat (still used by original template patterns)
                'activeAssignments'  => $activeAssignments,
                'analyticsVerdicts'  => $analyticsVerdicts,
            ];
        }

        // ---- Coach data ----
        $coachData = [];
        if ($isCoach) {
            $summaries = $dashboardService->getAthleteSummaries($user);
            $coachData = [
                'summaries'         => $summaries,
                'newMesocycleUrl'   => $this->generateUrl('mesocycle_new'),
                'newAssignmentUrl'  => $this->generateUrl('assignment_new'),
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'user'        => $user,
            'isAthlete'   => $isAthlete,
            'isCoach'     => $isCoach,
            'athleteData' => $athleteData,
            'coachData'   => $coachData,
        ]);
    }
}
