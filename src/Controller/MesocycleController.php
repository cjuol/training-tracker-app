<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Mesocycle;
use App\Entity\User;
use App\Form\MesocycleType;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\MesocycleRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutSessionRepository;
use App\Security\Voter\MesocycleVoter;
use App\Service\AssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mesocycles')]
#[IsGranted('ROLE_ENTRENADOR')]
class MesocycleController extends AbstractController
{
    #[Route('', name: 'mesocycle_index', methods: ['GET'])]
    public function index(MesocycleRepository $mesocycleRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $mesocycles = $mesocycleRepository->findByCoach($user);

        return $this->render('mesocycle/index.html.twig', [
            'mesocycles' => $mesocycles,
        ]);
    }

    #[Route('/new', name: 'mesocycle_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $mesocycle = new Mesocycle();
        $form = $this->createForm(MesocycleType::class, $mesocycle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $mesocycle->setCoach($user);

            $entityManager->persist($mesocycle);
            $entityManager->flush();

            $this->addFlash('success', 'Mesociclo creado correctamente.');

            return $this->redirectToRoute('mesocycle_show', ['id' => $mesocycle->getId()]);
        }

        return $this->render('mesocycle/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'mesocycle_show', methods: ['GET'])]
    public function show(Mesocycle $mesocycle, AssignedMesocycleRepository $assignedRepo, WorkoutSessionRepository $workoutSessionRepo): Response
    {
        $this->denyAccessUnlessGranted(MesocycleVoter::MESOCYCLE_EDIT, $mesocycle);

        $sessions = $workoutSessionRepo->findByMesocycleOrdered($mesocycle);

        $activeAssignments = $assignedRepo->findActiveByMesocycle($mesocycle);
        $assignedAthletes = array_map(fn ($a) => $a->getAthlete(), $activeAssignments);

        return $this->render('mesocycle/show.html.twig', [
            'mesocycle' => $mesocycle,
            'sessions' => $sessions,
            'assignedAthletes' => $assignedAthletes,
        ]);
    }

    #[Route('/{id}/regenerate-code', name: 'mesocycle_regenerate_code', methods: ['POST'])]
    public function regenerateCode(Request $request, Mesocycle $mesocycle, AssignmentService $assignmentService): Response
    {
        $this->denyAccessUnlessGranted(MesocycleVoter::MESOCYCLE_EDIT, $mesocycle);

        if (!$this->isCsrfTokenValid('regenerate-code-'.$mesocycle->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');
            return $this->redirectToRoute('mesocycle_show', ['id' => $mesocycle->getId()]);
        }

        $assignmentService->regenerateCode($mesocycle);
        $this->addFlash('success', 'Código regenerado. Las asignaciones activas anteriores han sido canceladas.');

        return $this->redirectToRoute('mesocycle_show', ['id' => $mesocycle->getId()]);
    }

    #[Route('/{id}/athlete/{athleteId}/stats', name: 'mesocycle_athlete_stats', methods: ['GET'])]
    public function athleteStats(
        Mesocycle $mesocycle,
        int $athleteId,
        AssignedMesocycleRepository $assignedRepo,
        \App\Repository\WorkoutLogRepository $workoutLogRepo,
        UserRepository $userRepository,
    ): Response {
        $this->denyAccessUnlessGranted(MesocycleVoter::MESOCYCLE_EDIT, $mesocycle);

        $athlete = $userRepository->find($athleteId);
        if ($athlete === null) {
            throw $this->createNotFoundException('Atleta no encontrado.');
        }

        $allAssignments = $assignedRepo->findByMesocycle($mesocycle);
        $athleteAssignments = array_values(array_filter(
            $allAssignments,
            fn ($a) => $a->getAthlete()->getId() === $athleteId
        ));

        $assignmentIds = array_map(fn ($a) => $a->getId(), $athleteAssignments);
        $logs = $workoutLogRepo->findByAthleteAndMesocycleAssignments($athlete, $assignmentIds);

        return $this->render('mesocycle/athlete_stats.html.twig', [
            'mesocycle' => $mesocycle,
            'athlete' => $athlete,
            'assignments' => $athleteAssignments,
            'logs' => $logs,
        ]);
    }

    #[Route('/{id}/edit', name: 'mesocycle_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Mesocycle $mesocycle, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(MesocycleVoter::MESOCYCLE_EDIT, $mesocycle);

        $form = $this->createForm(MesocycleType::class, $mesocycle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Mesociclo actualizado correctamente.');

            return $this->redirectToRoute('mesocycle_show', ['id' => $mesocycle->getId()]);
        }

        return $this->render('mesocycle/edit.html.twig', [
            'mesocycle' => $mesocycle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'mesocycle_delete', methods: ['POST'])]
    public function delete(Request $request, Mesocycle $mesocycle, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(MesocycleVoter::MESOCYCLE_DELETE, $mesocycle);

        if ($this->isCsrfTokenValid('delete-mesocycle-'.$mesocycle->getId(), $request->request->get('_token'))) {
            $entityManager->remove($mesocycle);
            $entityManager->flush();

            $this->addFlash('success', 'Mesociclo eliminado correctamente.');
        } else {
            $this->addFlash('error', 'Token CSRF inválido.');
        }

        return $this->redirectToRoute('mesocycle_index');
    }
}
