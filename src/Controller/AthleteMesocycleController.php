<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\WorkoutLogRepository;
use App\Service\AssignmentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/my-mesocycles')]
#[IsGranted('ROLE_ATLETA')]
class AthleteMesocycleController extends AbstractController
{
    #[Route('', name: 'athlete_mesocycles_index', methods: ['GET'])]
    public function index(AssignedMesocycleRepository $repo): Response
    {
        if (!$this->isGranted('ROLE_ATLETA') || $this->isGranted('ROLE_ENTRENADOR')) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $athlete */
        $athlete = $this->getUser();
        $allAssignments = $repo->findByAthlete($athlete);
        $activeAssignments = array_values(array_filter(
            $allAssignments,
            fn ($a) => $a->getStatus() === \App\Enum\AssignmentStatus::Active
        ));
        $pastAssignments = array_values(array_filter(
            $allAssignments,
            fn ($a) => $a->getStatus() !== \App\Enum\AssignmentStatus::Active
        ));

        return $this->render('athlete_mesocycle/index.html.twig', [
            'activeAssignments' => $activeAssignments,
            'pastAssignments' => $pastAssignments,
        ]);
    }

    #[Route('/{id}', name: 'athlete_mesocycle_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, AssignedMesocycleRepository $repo, WorkoutLogRepository $logRepo): Response
    {
        if (!$this->isGranted('ROLE_ATLETA') || $this->isGranted('ROLE_ENTRENADOR')) {
            throw $this->createAccessDeniedException();
        }

        /** @var User $athlete */
        $athlete = $this->getUser();

        $assignment = $repo->findForAthleteDetail($id);

        if ($assignment === null || $assignment->getAthlete()->getId() !== $athlete->getId()) {
            throw $this->createNotFoundException('Mesociclo no encontrado.');
        }

        $mesocycle = $assignment->getMesocycle();
        $sessions = $mesocycle->getWorkoutSessions()->toArray();
        usort($sessions, static fn ($a, $b) => $a->getOrderIndex() <=> $b->getOrderIndex());

        // Map of sessionId => WorkoutLog ID for in-progress logs in this assignment
        $inProgressMap = [];
        foreach ($logRepo->findInProgressByAthleteAndAssignmentIndexedBySession($athlete, $assignment->getId()) as $sessionId => $log) {
            $inProgressMap[$sessionId] = $log->getId();
        }

        return $this->render('athlete_mesocycle/show.html.twig', [
            'assignment'    => $assignment,
            'mesocycle'     => $mesocycle,
            'sessions'      => $sessions,
            'inProgressMap' => $inProgressMap,
        ]);
    }

    #[Route('/join', name: 'athlete_mesocycles_join', methods: ['POST'])]
    public function join(Request $request, AssignmentService $assignmentService): Response
    {
        if (!$this->isGranted('ROLE_ATLETA') || $this->isGranted('ROLE_ENTRENADOR')) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('athlete-join-mesocycle', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');
            return $this->redirectToRoute('athlete_mesocycles_index');
        }

        /** @var User $athlete */
        $athlete = $this->getUser();
        $code = trim((string) $request->request->get('invite_code', ''));

        if ($code === '') {
            $this->addFlash('error', 'Debes introducir un código de invitación.');
            return $this->redirectToRoute('athlete_mesocycles_index');
        }

        $startDateRaw = $request->request->get('start_date');
        try {
            $startDate = new \DateTimeImmutable($startDateRaw ?: 'today');
        } catch (\Exception) {
            $startDate = new \DateTimeImmutable('today');
        }

        try {
            $assignmentService->joinByCode($athlete, $code, $startDate);
            $this->addFlash('success', '¡Te has unido al mesociclo correctamente!');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('athlete_mesocycles_index');
    }
}
