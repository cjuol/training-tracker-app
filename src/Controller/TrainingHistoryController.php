<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\WorkoutLog;
use App\Repository\CoachAthleteRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutLogRepository;
use App\Security\Voter\HistoryVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TrainingHistoryController extends AbstractController
{
    private const PAGE_SIZE = 20;

    // -------------------------------------------------------------------------
    // Athlete routes
    // -------------------------------------------------------------------------

    #[Route('/history', name: 'history_index', methods: ['GET'])]
    #[IsGranted('ROLE_ATLETA')]
    public function index(
        Request $request,
        WorkoutLogRepository $workoutLogRepo,
    ): Response {
        /** @var User $athlete */
        $athlete = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $total = $workoutLogRepo->countByAthlete($athlete);
        $logs = $workoutLogRepo->findPaginatedByAthlete($athlete, $page, self::PAGE_SIZE);

        return $this->render('history/index.html.twig', [
            'logs' => $logs,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'total' => $total,
            'viewingAs' => 'athlete',
            'athlete' => $athlete,
        ]);
    }

    #[Route('/history/{workoutLogId}', name: 'history_show', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(
        int $workoutLogId,
        WorkoutLogRepository $workoutLogRepo,
    ): Response {
        $log = $workoutLogRepo->find($workoutLogId);

        if (!$log instanceof WorkoutLog) {
            throw $this->createNotFoundException('Registro de entrenamiento no encontrado.');
        }

        $this->denyAccessUnlessGranted(HistoryVoter::HISTORY_VIEW, $log);

        return $this->render('history/show.html.twig', [
            'log' => $log,
            'backRoute' => 'history_index',
            'backParams' => [],
            'viewingAs' => 'athlete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Coach routes
    // -------------------------------------------------------------------------

    #[Route('/coach/athletes/{athleteId}/history', name: 'coach_athlete_history', methods: ['GET'])]
    #[IsGranted('ROLE_ENTRENADOR')]
    public function coachAthleteHistory(
        int $athleteId,
        Request $request,
        UserRepository $userRepo,
        CoachAthleteRepository $coachAthleteRepo,
        WorkoutLogRepository $workoutLogRepo,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();
        $athlete = $userRepo->find($athleteId);

        if (!$athlete instanceof User) {
            throw $this->createNotFoundException('Atleta no encontrado.');
        }

        if (!$coachAthleteRepo->isAthleteOfCoach($coach, $athlete)) {
            throw $this->createAccessDeniedException('No tienes acceso al historial de este atleta.');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $total = $workoutLogRepo->countByAthlete($athlete);
        $logs = $workoutLogRepo->findPaginatedByAthlete($athlete, $page, self::PAGE_SIZE);

        return $this->render('history/index.html.twig', [
            'logs' => $logs,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'total' => $total,
            'viewingAs' => 'coach',
            'athlete' => $athlete,
        ]);
    }

    #[Route('/coach/athletes/{athleteId}/history/{workoutLogId}', name: 'coach_athlete_history_show', methods: ['GET'])]
    #[IsGranted('ROLE_ENTRENADOR')]
    public function coachAthleteHistoryShow(
        int $athleteId,
        int $workoutLogId,
        UserRepository $userRepo,
        CoachAthleteRepository $coachAthleteRepo,
        WorkoutLogRepository $workoutLogRepo,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();
        $athlete = $userRepo->find($athleteId);

        if (!$athlete instanceof User) {
            throw $this->createNotFoundException('Atleta no encontrado.');
        }

        if (!$coachAthleteRepo->isAthleteOfCoach($coach, $athlete)) {
            throw $this->createAccessDeniedException('No tienes acceso al historial de este atleta.');
        }

        $log = $workoutLogRepo->find($workoutLogId);

        if (!$log instanceof WorkoutLog) {
            throw $this->createNotFoundException('Registro de entrenamiento no encontrado.');
        }

        $this->denyAccessUnlessGranted(HistoryVoter::HISTORY_VIEW, $log);

        return $this->render('history/show.html.twig', [
            'log' => $log,
            'backRoute' => 'coach_athlete_history',
            'backParams' => ['athleteId' => $athleteId],
            'viewingAs' => 'coach',
        ]);
    }
}
