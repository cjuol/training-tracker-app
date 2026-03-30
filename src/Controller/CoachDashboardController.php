<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AssignmentStatus;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\BodyMeasurementRepository;
use App\Repository\CoachAthleteRepository;
use App\Repository\DailyStepsRepository;
use App\Repository\UserRepository;
use App\Service\Analytics\AnalyticsSnapshotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CoachDashboardController extends AbstractController
{
    private const PAGE_SIZE = 20;

    /**
     * Legacy route — redirects to the unified dashboard.
     */
    #[Route('/coach/dashboard', name: 'coach_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ENTRENADOR')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/coach/athletes/{athleteId}/analytics', name: 'coach_athlete_analytics', methods: ['GET'])]
    #[IsGranted('ROLE_ENTRENADOR')]
    public function athleteAnalytics(
        int $athleteId,
        UserRepository $userRepo,
        CoachAthleteRepository $coachAthleteRepo,
        AnalyticsSnapshotService $analyticsService,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();
        $athlete = $userRepo->find($athleteId);

        if (!$athlete instanceof User) {
            throw $this->createNotFoundException('Atleta no encontrado.');
        }

        if (!$coachAthleteRepo->isAthleteOfCoach($coach, $athlete)) {
            throw $this->createAccessDeniedException('No tienes acceso a los datos de este atleta.');
        }

        // Triggers recompute for stale/missing modules
        $verdicts = $analyticsService->getAll($athlete);

        return $this->render('coach/athlete_analytics.html.twig', [
            'athlete'  => $athlete,
            'verdicts' => $verdicts,
        ]);
    }

    #[Route('/coach/athletes/{athleteId}/measurements', name: 'coach_athlete_measurements', methods: ['GET'])]
    #[IsGranted('ROLE_ENTRENADOR')]
    public function athleteMeasurements(
        int $athleteId,
        Request $request,
        UserRepository $userRepo,
        CoachAthleteRepository $coachAthleteRepo,
        BodyMeasurementRepository $measurementRepo,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();
        $athlete = $userRepo->find($athleteId);

        if (!$athlete instanceof User) {
            throw $this->createNotFoundException('Atleta no encontrado.');
        }

        if (!$coachAthleteRepo->isAthleteOfCoach($coach, $athlete)) {
            throw $this->createAccessDeniedException('No tienes acceso a las mediciones de este atleta.');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $total = $measurementRepo->countByAthlete($athlete);
        $measurements = $measurementRepo->findByAthletePaginated($athlete, $page, self::PAGE_SIZE);
        $totalPages = max(1, (int) ceil($total / self::PAGE_SIZE));

        return $this->render('coach/athlete_measurements.html.twig', [
            'athlete' => $athlete,
            'measurements' => $measurements,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/coach/athletes/{athleteId}/steps', name: 'coach_athlete_steps', methods: ['GET'])]
    #[IsGranted('ROLE_ENTRENADOR')]
    public function athleteSteps(
        int $athleteId,
        Request $request,
        UserRepository $userRepo,
        CoachAthleteRepository $coachAthleteRepo,
        DailyStepsRepository $stepsRepo,
        AssignedMesocycleRepository $assignedMesocycleRepo,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();
        $athlete = $userRepo->find($athleteId);

        if (!$athlete instanceof User) {
            throw $this->createNotFoundException('Atleta no encontrado.');
        }

        if (!$coachAthleteRepo->isAthleteOfCoach($coach, $athlete)) {
            throw $this->createAccessDeniedException('No tienes acceso a los pasos de este atleta.');
        }

        $days = min(90, max(7, (int) ($request->query->get('days', 30))));
        // Clamp to known options
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $today = new \DateTimeImmutable('today');
        $from = $today->modify('-'.($days - 1).' days');

        $steps = $stepsRepo->findByUserForPeriod($athlete, $from, $today);

        // Resolve steps target from active mesocycle
        $activeAssignment = $assignedMesocycleRepo->findOneBy([
            'athlete' => $athlete,
            'status' => AssignmentStatus::Active,
        ]);
        $stepsTarget = 10000;
        if ($activeAssignment !== null) {
            $target = $activeAssignment->getMesocycle()->getDailyStepsTarget();
            if ($target !== null && $target > 0) {
                $stepsTarget = $target;
            }
        }

        // Compliance: % of days with data where steps >= target
        $metCount = 0;
        foreach ($steps as $entry) {
            if ($entry->getSteps() >= $stepsTarget) {
                ++$metCount;
            }
        }
        $compliance = count($steps) > 0
            ? round($metCount / count($steps) * 100, 1)
            : 0.0;

        return $this->render('coach/athlete_steps.html.twig', [
            'athlete' => $athlete,
            'steps' => $steps,
            'stepsTarget' => $stepsTarget,
            'days' => $days,
            'compliance' => $compliance,
        ]);
    }
}
