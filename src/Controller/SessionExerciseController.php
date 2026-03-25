<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SessionExercise;
use App\Entity\WorkoutSession;
use App\Form\SessionExerciseType;
use App\Security\Voter\MesocycleVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sessions/{sessionId}/exercises')]
#[IsGranted('ROLE_ENTRENADOR')]
class SessionExerciseController extends AbstractController
{
    private const CSRF_REORDER = 'reorder_session_exercises';

    #[Route('/new', name: 'session_exercise_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        int $sessionId,
        EntityManagerInterface $entityManager,
    ): Response {
        $session = $this->getSessionOrDeny($sessionId, $entityManager);

        $sessionExercise = new SessionExercise();
        $sessionExercise->setOrderIndex($session->getSessionExercises()->count() + 1);

        $form = $this->createForm(SessionExerciseType::class, $sessionExercise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sessionExercise->setWorkoutSession($session);
            $entityManager->persist($sessionExercise);
            $entityManager->flush();

            $this->addFlash('success', 'Ejercicio añadido correctamente.');

            return $this->redirectToRoute('mesocycle_show', [
                'id' => $session->getMesocycle()->getId(),
            ]);
        }

        return $this->render('session_exercise/new.html.twig', [
            'session' => $session,
            'mesocycle' => $session->getMesocycle(),
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'session_exercise_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        int $sessionId,
        SessionExercise $sessionExercise,
        EntityManagerInterface $entityManager,
    ): Response {
        $session = $this->getSessionOrDeny($sessionId, $entityManager);

        $form = $this->createForm(SessionExerciseType::class, $sessionExercise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Ejercicio actualizado correctamente.');

            return $this->redirectToRoute('mesocycle_show', [
                'id' => $session->getMesocycle()->getId(),
            ]);
        }

        return $this->render('session_exercise/edit.html.twig', [
            'session' => $session,
            'mesocycle' => $session->getMesocycle(),
            'sessionExercise' => $sessionExercise,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'session_exercise_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        int $sessionId,
        SessionExercise $sessionExercise,
        EntityManagerInterface $entityManager,
    ): Response {
        $session = $this->getSessionOrDeny($sessionId, $entityManager);

        if ($this->isCsrfTokenValid('delete-exercise-'.$sessionExercise->getId(), $request->request->get('_token'))) {
            $entityManager->remove($sessionExercise);
            $entityManager->flush();

            $this->addFlash('success', 'Ejercicio eliminado de la sesión.');
        } else {
            $this->addFlash('error', 'Token CSRF inválido.');
        }

        return $this->redirectToRoute('mesocycle_show', [
            'id' => $session->getMesocycle()->getId(),
        ]);
    }

    #[Route('/reorder', name: 'session_exercise_reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        int $sessionId,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $session = $this->getSessionOrDeny($sessionId, $entityManager);

        $data = json_decode($request->getContent(), true);

        if (!$this->isCsrfTokenValid(self::CSRF_REORDER, $data['_token'] ?? '')) {
            return $this->json(['error' => 'Token CSRF inválido'], 403);
        }

        $ids = $data['ids'] ?? [];

        foreach ($ids as $position => $id) {
            $exercise = $entityManager->find(SessionExercise::class, (int) $id);
            if (null !== $exercise && $exercise->getWorkoutSession()->getId() === $session->getId()) {
                $exercise->setOrderIndex($position + 1);
            }
        }

        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    private function getSessionOrDeny(int $sessionId, EntityManagerInterface $entityManager): WorkoutSession
    {
        $session = $entityManager->find(WorkoutSession::class, $sessionId);

        if (null === $session) {
            throw $this->createNotFoundException('Sesión no encontrada.');
        }

        $this->denyAccessUnlessGranted(MesocycleVoter::MESOCYCLE_EDIT, $session->getMesocycle());

        return $session;
    }
}
