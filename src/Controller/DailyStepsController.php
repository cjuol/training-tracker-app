<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\DailySteps;
use App\Entity\User;
use App\Form\DailyStepsType;
use App\Repository\DailyStepsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/steps')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DailyStepsController extends AbstractController
{
    // -------------------------------------------------------------------------
    // Index — show last N days + today's form
    // -------------------------------------------------------------------------

    #[Route('', name: 'daily_steps_index', methods: ['GET'])]
    public function index(Request $request, DailyStepsRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');

        $days = min(365, max(7, (int) ($request->query->get('days', 30))));
        $from = $today->modify('-'.($days - 1).' days');

        $recentSteps = $repo->findByUserForPeriod($user, $from, $today);
        $todayEntry = $repo->findByUserAndDate($user, $today);

        if ($todayEntry !== null) {
            $entry = $todayEntry;
        } else {
            $entry = new DailySteps();
            $entry->setDate($today);
        }

        $form = $this->createForm(DailyStepsType::class, $entry);

        return $this->render('daily_steps/index.html.twig', [
            'form' => $form,
            'recentSteps' => $recentSteps,
            'todayEntry' => $todayEntry,
            'days' => $days,
        ]);
    }

    // -------------------------------------------------------------------------
    // Upsert — create or update today's entry
    // -------------------------------------------------------------------------

    #[Route('', name: 'daily_steps_upsert', methods: ['POST'])]
    public function upsert(Request $request, DailyStepsRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');

        $existing = $repo->findByUserAndDate($user, $today);
        $entry = $existing ?? new DailySteps();

        $form = $this->createForm(DailyStepsType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($existing === null) {
                $entry->setUser($user);
                $em->persist($entry);
            }
            $em->flush();

            $this->addFlash('success', 'Pasos registrados correctamente.');

            return $this->redirectToRoute('daily_steps_index');
        }

        $recentSteps = $repo->findByUserForPeriod($user, $today->modify('-29 days'), $today);

        return $this->render('daily_steps/index.html.twig', [
            'form' => $form,
            'recentSteps' => $recentSteps,
            'todayEntry' => $existing,
            'days' => 30,
        ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    // -------------------------------------------------------------------------
    // Delete — remove a specific entry
    // -------------------------------------------------------------------------

    #[Route('/{id}/delete', name: 'daily_steps_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, DailyStepsRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $entry = $repo->find($id);

        if (!$entry instanceof DailySteps) {
            throw $this->createNotFoundException('Entrada no encontrada.');
        }

        if ($entry->getUser() !== $user) {
            throw $this->createAccessDeniedException('No puedes eliminar esta entrada.');
        }

        if (!$this->isCsrfTokenValid('delete_steps_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $em->remove($entry);
        $em->flush();

        $this->addFlash('success', 'Entrada eliminada correctamente.');

        return $this->redirectToRoute('daily_steps_index');
    }
}
