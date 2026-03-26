<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\WorkoutLogRepository;
use App\Service\CoachDashboardService;
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
        CoachDashboardService $dashboardService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $isAthlete = $this->isGranted('ROLE_ATLETA');
        $isCoach   = $this->isGranted('ROLE_ENTRENADOR');

        // ---- Athlete data ----
        $athleteData = [];
        $sessionInProgress = [];
        if ($isAthlete) {
            $activeAssignments = $assignedRepo->findActiveByAthlete($user);

            // Single query: all in-progress logs keyed by session ID
            $inProgressMap = $workoutLogRepo->findInProgressByAthleteIndexedBySession($user);
            foreach ($inProgressMap as $sessionId => $log) {
                $sessionInProgress[$sessionId] = $log->getId();
            }

            $athleteData = [
                'activeAssignments' => $activeAssignments,
                'sessionInProgress' => $sessionInProgress,
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
