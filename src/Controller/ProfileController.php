<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BodyMeasurement;
use App\Entity\DailyHeartRate;
use App\Entity\SleepLog;
use App\Entity\User;
use App\Form\BodyMeasurementType;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\BodyMeasurementRepository;
use App\Repository\DailyHeartRateRepository;
use App\Repository\DailyStepsRepository;
use App\Repository\DailyWellnessMetricsRepository;
use App\Repository\SetLogRepository;
use App\Repository\SleepLogRepository;
use App\Repository\WorkoutLogRepository;
use App\Security\Voter\BodyMeasurementVoter;
use App\Service\Analytics\AnalyticsSnapshotService;
use Cjuol\StatGuard\RobustStats;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Image;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    private const PER_PAGE = 10;
    private const UPLOAD_DIR = 'uploads/profile_pictures';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    // -------------------------------------------------------------------------
    // Athlete profile index
    // -------------------------------------------------------------------------

    #[Route('', name: 'profile_index', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(
        BodyMeasurementRepository $repo,
        AssignedMesocycleRepository $assignedRepo,
        DailyStepsRepository $dailyStepsRepo,
        WorkoutLogRepository $workoutLogRepo,
        SleepLogRepository $sleepLogRepo,
        DailyHeartRateRepository $heartRateRepo,
        AnalyticsSnapshotService $analyticsService,
        DailyWellnessMetricsRepository $wellnessRepo,
        SetLogRepository $setLogRepo,
    ): Response {
        /** @var User $athlete */
        $athlete = $this->getUser();
        $today = new \DateTimeImmutable('today');
        $monday = new \DateTimeImmutable('monday this week midnight');

        $latestMeasurement = $repo->findLatestByAthlete($athlete);
        $bodyMeasurements = $repo->findByAthleteAndDateRange($athlete, $today->modify('-4 weeks'), $today);

        $activeAssignments = $assignedRepo->findActiveByAthlete($athlete);
        $activeMesocycle = $activeAssignments[0] ?? null;

        $todayEntry = $dailyStepsRepo->findByUserAndDate($athlete, $today);
        $todaySteps = $todayEntry?->getSteps() ?? 0;
        $stepsTarget = $activeMesocycle?->getMesocycle()->getDailyStepsTarget();

        $recentHistory = $workoutLogRepo->findRecentByUser($athlete, 10);

        $fitbitToken = $athlete->getFitbitToken();
        $fitbitConnected = $fitbitToken !== null && $fitbitToken->isValid();
        $sleepMinutes = 0;
        $cardioMinutes = 0;
        $restingHR = null;

        if ($fitbitConnected) {
            $lastNightSleep = $sleepLogRepo->findByUserAndDate($athlete, new \DateTimeImmutable('yesterday'));
            $sleepMinutes = $lastNightSleep?->getDurationMinutes() ?? 0;

            $todayHR = $heartRateRepo->findByUserAndDate($athlete, $today);
            $restingHR = $todayHR?->getRestingHeartRate();
            if ($todayHR) {
                foreach ($todayHR->getZones() as $zone) {
                    if (in_array($zone['name'] ?? '', ['Cardio', 'Peak'], true)) {
                        $cardioMinutes += (int) ($zone['minutes'] ?? 0);
                    }
                }
            }
        }

        // Analytics snapshot (TTL-cached, 6h)
        $scores = $analyticsService->getAll($athlete);

        // Latest wellness metrics
        $wellness = $wellnessRepo->findLatestByUser($athlete);

        // Weekly tonnage & workout count
        $weeklyTonnage = $setLogRepo->findTonnageForPeriod($athlete, $monday, $today);
        $weekWorkouts = count($workoutLogRepo->findCompletedByAthleteInDateRange($athlete, $monday, $today));

        // RHR sparkline with server-side Huber mean + MAD color computation
        $rhrRecords = $heartRateRepo->findRecentByUser($athlete, 14);
        $rhrValues = [];
        foreach ($rhrRecords as $record) {
            $rhr = $record->getRestingHeartRate();
            if ($rhr !== null) {
                $rhrValues[] = (float) $rhr;
            }
        }

        $sparklineData = [];
        if (count($rhrValues) >= 2) {
            $rs = new RobustStats();
            $mean = $rs->getHuberMean($rhrValues);
            $mad = $rs->getMad($rhrValues);
            foreach ($rhrRecords as $record) {
                $rhr = $record->getRestingHeartRate();
                if ($rhr === null) {
                    continue;
                }
                $deviation = abs((float) $rhr - $mean);
                if ($deviation <= $mad) {
                    $color = 'green';
                } elseif ($deviation <= 2 * $mad) {
                    $color = 'amber';
                } else {
                    $color = 'red';
                }
                $sparklineData[] = [
                    'date'      => $record->getDate()->format('Y-m-d'),
                    'value'     => (float) $rhr,
                    'color'     => $color,
                    'huberMean' => $mean,
                    'mad'       => $mad,
                ];
            }
        } else {
            foreach ($rhrRecords as $record) {
                $rhr = $record->getRestingHeartRate();
                if ($rhr === null) {
                    continue;
                }
                $sparklineData[] = [
                    'date'      => $record->getDate()->format('Y-m-d'),
                    'value'     => (float) $rhr,
                    'color'     => 'gray',
                    'huberMean' => null,
                    'mad'       => null,
                ];
            }
        }
        $sparklineJson = json_encode($sparklineData, \JSON_THROW_ON_ERROR);

        return $this->render('profile/index.html.twig', [
            'activeMesocycle'  => $activeMesocycle,
            'latestMeasurement' => $latestMeasurement,
            'bodyMeasurements' => $bodyMeasurements,
            'todaySteps'       => $todaySteps,
            'stepsTarget'      => $stepsTarget,
            'stepsGoal'        => 10000,
            'sleepGoal'        => 480,
            'recentHistory'    => $recentHistory,
            'fitbitToken'      => $fitbitToken,
            'fitbitConnected'  => $fitbitConnected,
            'sleepMinutes'     => $sleepMinutes,
            'sleepTarget'      => 480,
            'cardioMinutes'    => $cardioMinutes,
            'cardioTarget'     => 22,
            'restingHR'        => $restingHR,
            'scores'           => $scores,
            'wellness'         => $wellness,
            'weeklyTonnage'    => $weeklyTonnage,
            'weekWorkouts'     => $weekWorkouts,
            'sparklineJson'    => $sparklineJson,
        ]);
    }

    // -------------------------------------------------------------------------
    // Wellness data JSON endpoint
    // -------------------------------------------------------------------------

    #[Route('/wellness-data', name: 'profile_wellness_data', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function wellnessData(DailyWellnessMetricsRepository $wellnessRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $records = $wellnessRepo->findLast14ByUser($user);

        $data = array_map(static function (\App\Entity\DailyWellnessMetrics $w): array {
            return [
                'date'                     => $w->getDate()->format('Y-m-d'),
                'rmssd'                    => $w->getRmssd(),
                'deepRmssd'                => $w->getDeepRmssd(),
                'spo2Avg'                  => $w->getSpo2Avg(),
                'spo2Min'                  => $w->getSpo2Min(),
                'spo2Max'                  => $w->getSpo2Max(),
                'breathingRate'            => $w->getBreathingRate(),
                'skinTemperatureRelative'  => $w->getSkinTemperatureRelative(),
            ];
        }, $records);

        return new JsonResponse($data);
    }

    // -------------------------------------------------------------------------
    // Activity calendar JSON endpoint
    // -------------------------------------------------------------------------

    #[Route('/activity-calendar', name: 'profile_activity_calendar', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function activityCalendar(WorkoutLogRepository $workoutLogRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $from = new \DateTimeImmutable('-90 days');
        $to = new \DateTimeImmutable('today');

        $logs = $workoutLogRepo->findCompletedByAthleteInDateRange($user, $from, $to);

        $dateCountMap = [];
        foreach ($logs as $log) {
            $dateStr = $log->getStartTime()->format('Y-m-d');
            if (!isset($dateCountMap[$dateStr])) {
                $dateCountMap[$dateStr] = 0;
            }
            ++$dateCountMap[$dateStr];
        }

        $result = [];
        foreach ($dateCountMap as $date => $count) {
            $result[] = ['date' => $date, 'count' => $count];
        }

        return new JsonResponse($result);
    }

    // -------------------------------------------------------------------------
    // Personal records JSON endpoint
    // -------------------------------------------------------------------------

    #[Route('/personal-records', name: 'profile_personal_records', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function personalRecords(SetLogRepository $setLogRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $rows = $setLogRepo->findPersonalRecordsByUser($user);

        // Reduce to all-time max per exercise (one row per exercise name)
        $best = [];
        foreach ($rows as $row) {
            $name = $row['exercise_name'];
            if (!isset($best[$name]) || $row['max_weight'] > $best[$name]['max_weight']) {
                $best[$name] = $row;
            }
        }

        // Sort alphabetically and take first 20
        ksort($best);
        $best = array_slice(array_values($best), 0, 20);

        $data = array_map(static function (array $r): array {
            $date = $r['date'];
            if ($date instanceof \DateTimeInterface) {
                $date = $date->format('Y-m-d');
            }

            return [
                'exercise'  => $r['exercise_name'],
                'maxWeight' => $r['max_weight'],
                'date'      => $date,
            ];
        }, $best);

        return new JsonResponse($data);
    }

    // -------------------------------------------------------------------------
    // Profile picture — show form
    // -------------------------------------------------------------------------

    #[Route('/picture', name: 'profile_picture_show', methods: ['GET'])]
    #[IsGranted('ROLE_ATLETA')]
    public function picture(): Response
    {
        $form = $this->createPictureForm();

        return $this->render('profile/picture.html.twig', [
            'form' => $form,
        ]);
    }

    // -------------------------------------------------------------------------
    // Profile picture — handle upload
    // -------------------------------------------------------------------------

    #[Route('/picture', name: 'profile_picture_upload', methods: ['POST'])]
    #[IsGranted('ROLE_ATLETA')]
    public function uploadPicture(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createPictureForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('profile/picture.html.twig', [
                'form' => $form,
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile */
        $uploadedFile = $form->get('picture')->getData();

        // Delete old file if one exists
        if ($user->getProfilePictureFilename() !== null) {
            $oldPath = $this->projectDir.'/public/'.self::UPLOAD_DIR.'/'.$user->getProfilePictureFilename();
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Generate UUID filename, preserving extension
        $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension();
        $newFilename = Uuid::v4()->toRfc4122().'.'.$extension;
        $uploadedFile->move($this->projectDir.'/public/'.self::UPLOAD_DIR, $newFilename);

        $user->setProfilePictureFilename($newFilename);
        $em->flush();

        $this->addFlash('success', 'Foto de perfil actualizada correctamente.');

        return $this->redirectToRoute('profile_picture_show');
    }

    // -------------------------------------------------------------------------
    // Profile picture — handle delete
    // -------------------------------------------------------------------------

    #[Route('/picture/delete', name: 'profile_picture_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ATLETA')]
    public function deletePicture(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('delete_profile_picture', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        if ($user->getProfilePictureFilename() !== null) {
            $filePath = $this->projectDir.'/public/'.self::UPLOAD_DIR.'/'.$user->getProfilePictureFilename();
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            $user->setProfilePictureFilename(null);
            $em->flush();
        }

        $this->addFlash('success', 'Foto de perfil eliminada correctamente.');

        return $this->redirectToRoute('profile_picture_show');
    }

    // -------------------------------------------------------------------------
    // Create measurement
    // -------------------------------------------------------------------------

    #[Route('/measurements/new', name: 'profile_measurement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ATLETA')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $athlete */
        $athlete = $this->getUser();
        $measurement = new BodyMeasurement();

        $form = $this->createForm(BodyMeasurementType::class, $measurement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $measurement->setAthlete($athlete);
            $em->persist($measurement);
            $em->flush();

            $this->addFlash('success', 'Medición registrada correctamente.');

            return $this->redirectToRoute('profile_index');
        }

        return $this->render('profile/new.html.twig', [
            'form' => $form,
        ]);
    }

    // -------------------------------------------------------------------------
    // Edit measurement
    // -------------------------------------------------------------------------

    #[Route('/measurements/{id}/edit', name: 'profile_measurement_edit', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(
        int $id,
        Request $request,
        BodyMeasurementRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $measurement = $repo->find($id);

        if (!$measurement instanceof BodyMeasurement) {
            throw $this->createNotFoundException('Medición no encontrada.');
        }

        $this->denyAccessUnlessGranted(BodyMeasurementVoter::MEASUREMENT_EDIT, $measurement);

        $form = $this->createForm(BodyMeasurementType::class, $measurement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $measurement->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', 'Medición actualizada correctamente.');

            return $this->redirectToRoute('profile_index');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'measurement' => $measurement,
        ]);
    }

    // -------------------------------------------------------------------------
    // Delete measurement
    // -------------------------------------------------------------------------

    #[Route('/measurements/{id}/delete', name: 'profile_measurement_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(
        int $id,
        Request $request,
        BodyMeasurementRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $measurement = $repo->find($id);

        if (!$measurement instanceof BodyMeasurement) {
            throw $this->createNotFoundException('Medición no encontrada.');
        }

        $this->denyAccessUnlessGranted(BodyMeasurementVoter::MEASUREMENT_DELETE, $measurement);

        if (!$this->isCsrfTokenValid('delete_measurement_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $em->remove($measurement);
        $em->flush();

        $this->addFlash('success', 'Medición eliminada correctamente.');

        return $this->redirectToRoute('profile_index');
    }

    // -------------------------------------------------------------------------
    // JSON paginated list (for Stimulus controller)
    // -------------------------------------------------------------------------

    #[Route('/measurements/list', name: 'profile_measurement_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(Request $request, BodyMeasurementRepository $repo, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        /** @var User $athlete */
        $athlete = $this->getUser();
        $page = max(1, (int) $request->query->get('page', 1));
        $total = $repo->countByAthlete($athlete);
        $items = $repo->findByAthletePaginated($athlete, $page, self::PER_PAGE);

        $itemsData = array_map(static function (BodyMeasurement $measurement) use ($csrfTokenManager): array {
            return [
                'id' => $measurement->getId(),
                'measurement_date' => $measurement->getMeasurementDate()->format('Y-m-d'),
                'weight_kg' => $measurement->getWeightKg(),
                'chest_cm' => $measurement->getChestCm(),
                'waist_cm' => $measurement->getWaistCm(),
                'hips_cm' => $measurement->getHipsCm(),
                'arms_cm' => $measurement->getArmsCm(),
                'notes' => $measurement->getNotes(),
                'csrf_token' => $csrfTokenManager->getToken('delete_measurement_'.$measurement->getId())->getValue(),
            ];
        }, $items);

        return new JsonResponse([
            'items' => $itemsData,
            'total' => $total,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'hasMore' => ($page * self::PER_PAGE) < $total,
        ]);
    }

    // -------------------------------------------------------------------------
    // Sleep detail page
    // -------------------------------------------------------------------------

    #[Route('/sleep', name: 'profile_sleep', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function sleep(SleepLogRepository $sleepLogRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $fitbitToken = $user->getFitbitToken();
        $sleepMinutes = 0;
        if ($fitbitToken && $fitbitToken->isValid()) {
            $lastNight = $sleepLogRepo->findByUserAndDate($user, new \DateTimeImmutable('yesterday'));
            $sleepMinutes = $lastNight?->getDurationMinutes() ?? 0;
        }

        return $this->render('profile/sleep.html.twig', [
            'fitbitToken'  => $fitbitToken,
            'sleepMinutes' => $sleepMinutes,
            'sleepTarget'  => 480,
            'dataUrl'      => $this->generateUrl('profile_sleep_data', ['days' => 30]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Cardio detail page
    // -------------------------------------------------------------------------

    #[Route('/cardio', name: 'profile_cardio', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function cardio(DailyHeartRateRepository $heartRateRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $fitbitToken   = $user->getFitbitToken();
        $cardioMinutes = 0;
        $restingHR     = null;
        if ($fitbitToken && $fitbitToken->isValid()) {
            $todayHR   = $heartRateRepo->findByUserAndDate($user, new \DateTimeImmutable('today'));
            $restingHR = $todayHR?->getRestingHeartRate();
            if ($todayHR) {
                foreach ($todayHR->getZones() as $zone) {
                    if (in_array($zone['name'] ?? '', ['Cardio', 'Peak'], true)) {
                        $cardioMinutes += (int) ($zone['minutes'] ?? 0);
                    }
                }
            }
        }

        return $this->render('profile/cardio.html.twig', [
            'fitbitToken'   => $fitbitToken,
            'cardioMinutes' => $cardioMinutes,
            'cardioTarget'  => 22,
            'restingHR'     => $restingHR,
            'dataUrl'       => $this->generateUrl('profile_cardio_data', ['days' => 30]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Measurements history page
    // -------------------------------------------------------------------------

    #[Route('/measurements', name: 'profile_measurements', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function measurements(BodyMeasurementRepository $repo): Response
    {
        /** @var User $athlete */
        $athlete = $this->getUser();
        $total = $repo->countByAthlete($athlete);

        return $this->render('profile/measurements.html.twig', [
            'total' => $total,
        ]);
    }

    // -------------------------------------------------------------------------
    // Fitbit JSON data endpoints
    // -------------------------------------------------------------------------

    #[Route('/sleep-data', name: 'profile_sleep_data', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function sleepData(Request $request, SleepLogRepository $sleepLogRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $days = max(1, min(90, (int) $request->query->get('days', 7)));
        $records = $sleepLogRepo->findRecentByUser($user, $days);
        $records = array_reverse($records);

        $data = array_map(static function (SleepLog $log): array {
            $stages = $log->getStages() ?? [];
            $hasStages = !empty($stages);
            $entry = [
                'date'            => $log->getDate()->format('Y-m-d'),
                'durationMinutes' => $log->getDurationMinutes(),
            ];
            if ($hasStages) {
                $entry['stages'] = [
                    'deep'  => (int) ($stages['deep']['minutes']  ?? $stages['deep']  ?? 0),
                    'light' => (int) ($stages['light']['minutes'] ?? $stages['light'] ?? 0),
                    'rem'   => (int) ($stages['rem']['minutes']   ?? $stages['rem']   ?? 0),
                    'wake'  => (int) ($stages['wake']['minutes']  ?? $stages['wake']  ?? 0),
                ];
            }

            return $entry;
        }, $records);

        return new JsonResponse($data);
    }

    #[Route('/cardio-intraday', name: 'profile_cardio_intraday', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function cardioIntraday(DailyHeartRateRepository $heartRateRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $now     = new \DateTimeImmutable('now');
        $cutoff  = $now->modify('-24 hours');

        $today     = $heartRateRepo->findByUserAndDate($user, new \DateTimeImmutable('today'));
        $yesterday = $heartRateRepo->findByUserAndDate($user, new \DateTimeImmutable('yesterday'));

        // Collect all entries with their record date
        $allEntries = [];
        foreach ([$yesterday, $today] as $record) {
            if ($record === null) {
                continue;
            }
            $dataset = $record->getIntradayData();
            if (empty($dataset)) {
                continue;
            }
            $recordDate = $record->getDate()->format('Y-m-d');
            foreach ($dataset as $entry) {
                $allEntries[] = ['date' => $recordDate, 'time' => $entry['time'], 'value' => (int) $entry['value']];
            }
        }

        if (empty($allEntries)) {
            return new JsonResponse([]);
        }

        // Filter to last 24h window and bucket by hour
        $buckets = [];
        foreach ($allEntries as $entry) {
            $entryDateTime = new \DateTimeImmutable($entry['date'].' '.$entry['time']);
            if ($entryDateTime < $cutoff || $entryDateTime > $now) {
                continue;
            }
            $hour = (int) $entryDateTime->format('H');
            $buckets[$hour][] = $entry['value'];
        }

        if (empty($buckets)) {
            return new JsonResponse([]);
        }

        ksort($buckets);

        $result = [];
        foreach ($buckets as $hour => $values) {
            $result[] = [
                'hour'    => sprintf('%02d:00', $hour),
                'avgBpm'  => (int) round(array_sum($values) / count($values)),
            ];
        }

        return new JsonResponse($result);
    }

    #[Route('/cardio-data', name: 'profile_cardio_data', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function cardioData(Request $request, DailyHeartRateRepository $heartRateRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $days = max(1, min(90, (int) $request->query->get('days', 7)));
        $records = $heartRateRepo->findRecentByUser($user, $days);
        $records = array_reverse($records);

        $data = array_map(static function (DailyHeartRate $hr): array {
            return [
                'date'             => $hr->getDate()->format('Y-m-d'),
                'restingHeartRate' => $hr->getRestingHeartRate(),
                'zones'            => array_map(static fn(array $z): array => [
                    'name'    => $z['name'] ?? '',
                    'minutes' => (int) ($z['minutes'] ?? 0),
                ], $hr->getZones() ?? []),
            ];
        }, $records);

        return new JsonResponse($data);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createPictureForm(): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder()
            ->add('picture', FileType::class, [
                'label' => 'Foto de perfil',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Sólo se permiten imágenes JPEG, PNG o WebP.',
                        maxSizeMessage: 'La imagen no puede superar los 2 MB.',
                    ),
                ],
            ])
            ->getForm();
    }
}
