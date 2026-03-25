<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Mesocycle;
use App\Entity\WorkoutSession;
use App\Form\WorkoutSessionType;
use App\Security\Voter\MesocycleVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mesocycles/{mesocycleId}/sessions')]
#[IsGranted('ROLE_ENTRENADOR')]
class WorkoutSessionController extends AbstractController
{
    private const CSRF_REORDER = 'reorder_workout_sessions';

    #[Route('/new', name: 'workout_session_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        int $mesocycleId,
        EntityManagerInterface $entityManager,
    ): Response {
        $mesocycle = $this->getMesocycleOrDeny($mesocycleId, $entityManager);

        $session = new WorkoutSession();
        // Set default orderIndex to the next available position
        $session->setOrderIndex($mesocycle->getWorkoutSessions()->count() + 1);

        $form = $this->createForm(WorkoutSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session->setMesocycle($mesocycle);
            $entityManager->persist($session);
            $entityManager->flush();

            $this->addFlash('success', 'Sesión añadida correctamente.');

            return $this->redirectToRoute('mesocycle_show', ['id' => $mesocycleId]);
        }

        return $this->render('workout_session/new.html.twig', [
            'mesocycle' => $mesocycle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'workout_session_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        int $mesocycleId,
        WorkoutSession $session,
        EntityManagerInterface $entityManager,
    ): Response {
        $mesocycle = $this->getMesocycleOrDeny($mesocycleId, $entityManager);

        $form = $this->createForm(WorkoutSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Sesión actualizada correctamente.');

            return $this->redirectToRoute('mesocycle_show', ['id' => $mesocycleId]);
        }

        return $this->render('workout_session/edit.html.twig', [
            'mesocycle' => $mesocycle,
            'session' => $session,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'workout_session_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        int $mesocycleId,
        WorkoutSession $session,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->getMesocycleOrDeny($mesocycleId, $entityManager);

        if ($this->isCsrfTokenValid('delete-session-'.$session->getId(), $request->request->get('_token'))) {
            $entityManager->remove($session);
            $entityManager->flush();

            $this->addFlash('success', 'Sesión eliminada correctamente.');
        } else {
            $this->addFlash('error', 'Token CSRF inválido.');
        }

        return $this->redirectToRoute('mesocycle_show', ['id' => $mesocycleId]);
    }

    #[Route('/reorder', name: 'workout_session_reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        int $mesocycleId,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $this->getMesocycleOrDeny($mesocycleId, $entityManager);

        $data = json_decode($request->getContent(), true);

        if (!$this->isCsrfTokenValid(self::CSRF_REORDER, $data['_token'] ?? '')) {
            return $this->json(['error' => 'Token CSRF inválido'], 403);
        }

        $ids = $data['ids'] ?? [];

        foreach ($ids as $position => $id) {
            $session = $entityManager->find(WorkoutSession::class, (int) $id);
            if (null !== $session && $session->getMesocycle()->getId() === $mesocycleId) {
                $session->setOrderIndex($position + 1);
            }
        }

        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    private function getMesocycleOrDeny(int $mesocycleId, EntityManagerInterface $entityManager): Mesocycle
    {
        $mesocycle = $entityManager->find(Mesocycle::class, $mesocycleId);

        if (null === $mesocycle) {
            throw $this->createNotFoundException('Mesociclo no encontrado.');
        }

        $this->denyAccessUnlessGranted(MesocycleVoter::MESOCYCLE_EDIT, $mesocycle);

        return $mesocycle;
    }
}
