<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AssignedMesocycle;
use App\Entity\SetLog;
use App\Entity\User;
use App\Entity\WorkoutLog;
use App\Entity\WorkoutSession;
use App\Enum\WorkoutStatus;
use App\Repository\SetLogRepository;
use App\Security\Voter\WorkoutLogVoter;
use App\Service\WorkoutExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/workout')]
class WorkoutExecutionController extends AbstractController
{
    // -------------------------------------------------------------------------
    // Start a workout
    // -------------------------------------------------------------------------

    /**
     * Creates a new WorkoutLog and redirects to the execution view.
     */
    #[Route('/start/{assignedMesocycleId}/{sessionId}', name: 'workout_start', methods: ['POST'])]
    #[IsGranted('ROLE_ATLETA')]
    public function start(
        int $assignedMesocycleId,
        int $sessionId,
        WorkoutExecutionService $workoutExecutionService,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $athlete */
        $athlete = $this->getUser();

        $assignment = $em->find(AssignedMesocycle::class, $assignedMesocycleId);
        $session = $em->find(WorkoutSession::class, $sessionId);

        if (null === $assignment || null === $session) {
            throw $this->createNotFoundException('Asignación o sesión no encontrada.');
        }

        try {
            $workoutLog = $workoutExecutionService->startWorkout($athlete, $assignment, $session);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('workout_show', ['workoutLogId' => $workoutLog->getId()]);
    }

    // -------------------------------------------------------------------------
    // Main execution view
    // -------------------------------------------------------------------------

    #[Route('/{workoutLogId}', name: 'workout_show', methods: ['GET'])]
    public function show(
        int $workoutLogId,
        EntityManagerInterface $em,
        SetLogRepository $setLogRepository,
    ): Response {
        $workoutLog = $em->find(WorkoutLog::class, $workoutLogId);

        if (null === $workoutLog) {
            throw $this->createNotFoundException('Entrenamiento no encontrado.');
        }

        $this->denyAccessUnlessGranted(WorkoutLogVoter::WORKOUT_VIEW, $workoutLog);

        $session = $workoutLog->getWorkoutSession();
        $sessionExercises = $session->getOrderedExercises()->toArray();

        // Build map: sessionExerciseId => SetLog[]
        $setLogsByExercise = [];
        foreach ($sessionExercises as $se) {
            $setLogsByExercise[$se->getId()] = $setLogRepository->findForExercise($workoutLog, $se);
        }

        // Build workout state JSON for Stimulus
        $exercisesState = array_map(static function ($se) use ($setLogsByExercise): array {
            return [
                'id' => $se->getId(),
                'seriesType' => $se->getSeriesType()->value,
                'superseriesGroup' => $se->getSuperseriesGroup(),
                'targetSets' => $se->getTargetSets(),
                'loggedSets' => count($setLogsByExercise[$se->getId()] ?? []),
            ];
        }, $sessionExercises);

        $workoutState = [
            'workoutLogId' => $workoutLog->getId(),
            'currentExerciseId' => $workoutLog->getCurrentExerciseId(),
            'exercises' => $exercisesState,
            'isComplete' => WorkoutStatus::Completed === $workoutLog->getStatus(),
        ];

        return $this->render('workout/session.html.twig', [
            'workoutLog' => $workoutLog,
            'sessionExercises' => $sessionExercises,
            'setLogsByExercise' => $setLogsByExercise,
            'workoutState' => $workoutState,
        ]);
    }

    // -------------------------------------------------------------------------
    // Log a set (JSON endpoint)
    // -------------------------------------------------------------------------

    #[Route('/{workoutLogId}/set/log', name: 'workout_set_log', methods: ['POST'])]
    public function logSet(
        int $workoutLogId,
        Request $request,
        EntityManagerInterface $em,
        WorkoutExecutionService $workoutExecutionService,
        SetLogRepository $setLogRepo,
    ): JsonResponse {
        $workoutLog = $em->find(WorkoutLog::class, $workoutLogId);

        if (null === $workoutLog) {
            return $this->json(['error' => 'Entrenamiento no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(WorkoutLogVoter::WORKOUT_LOG_SET, $workoutLog);

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'JSON inválido'], 400);
        }

        if (!isset($data['sessionExerciseId'])) {
            return $this->json(['error' => 'sessionExerciseId es requerido.'], Response::HTTP_BAD_REQUEST);
        }

        $sessionExercise = $em->find(\App\Entity\SessionExercise::class, (int) $data['sessionExerciseId']);

        if (null === $sessionExercise) {
            return $this->json(['error' => 'Ejercicio de sesión no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($sessionExercise->getWorkoutSession()->getId() !== $workoutLog->getWorkoutSession()->getId()) {
            throw $this->createAccessDeniedException('El ejercicio no pertenece a esta sesión.');
        }

        try {
            $setLog = $workoutExecutionService->logSet($workoutLog, $sessionExercise, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        // Reload to get updated currentExerciseId
        $em->refresh($workoutLog);

        // Check if all exercises have met their target sets (workout complete signal)
        $session = $workoutLog->getWorkoutSession();
        $sessionExercises = $session->getOrderedExercises()->toArray();
        $countMap = $setLogRepo->countByWorkoutLogGrouped($workoutLog);
        $allDone = true;
        foreach ($sessionExercises as $se) {
            if (($countMap[$se->getId()] ?? 0) < $se->getTargetSets()) {
                $allDone = false;
                break;
            }
        }

        return $this->json([
            'success' => true,
            'setLog' => [
                'id' => $setLog->getId(),
                'setNumber' => $setLog->getSetNumber(),
                'reps' => $setLog->getReps(),
                'weight' => $setLog->getWeight(),
                'rir' => $setLog->getRir(),
                'timeDuration' => $setLog->getTimeDuration(),
                'distance' => $setLog->getDistance(),
                'kcal' => $setLog->getKcal(),
                'observacion' => $setLog->getObservacion(),
            ],
            'restSuggested' => $sessionExercise->getRestSeconds() ?? 90,
            'newCurrentExerciseId' => $workoutLog->getCurrentExerciseId(),
            'workoutComplete' => $allDone,
        ]);
    }

    // -------------------------------------------------------------------------
    // Update rest time (JSON endpoint)
    // -------------------------------------------------------------------------

    #[Route('/{workoutLogId}/set/{setLogId}/rest', name: 'workout_set_rest', methods: ['POST'])]
    public function updateRest(
        int $workoutLogId,
        int $setLogId,
        Request $request,
        EntityManagerInterface $em,
        WorkoutExecutionService $workoutExecutionService,
    ): JsonResponse {
        $workoutLog = $em->find(WorkoutLog::class, $workoutLogId);

        if (null === $workoutLog) {
            return $this->json(['error' => 'Entrenamiento no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(WorkoutLogVoter::WORKOUT_LOG_SET, $workoutLog);

        $setLog = $em->find(SetLog::class, $setLogId);

        if (null === $setLog || $setLog->getWorkoutLog()->getId() !== $workoutLog->getId()) {
            return $this->json(['error' => 'Serie no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $restSeconds = isset($data['restSeconds']) ? (int) $data['restSeconds'] : 0;

        $workoutExecutionService->updateRestTime($setLog, $restSeconds);

        return $this->json(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Complete workout
    // -------------------------------------------------------------------------

    #[Route('/{workoutLogId}/complete', name: 'workout_complete', methods: ['POST'])]
    public function complete(
        int $workoutLogId,
        EntityManagerInterface $em,
        WorkoutExecutionService $workoutExecutionService,
    ): Response {
        $workoutLog = $em->find(WorkoutLog::class, $workoutLogId);

        if (null === $workoutLog) {
            throw $this->createNotFoundException('Entrenamiento no encontrado.');
        }

        $this->denyAccessUnlessGranted(WorkoutLogVoter::WORKOUT_LOG_SET, $workoutLog);

        $workoutExecutionService->completeWorkout($workoutLog);

        $this->addFlash('success', '¡Entrenamiento completado! Buen trabajo.');

        return $this->redirectToRoute('app_dashboard');
    }
}
