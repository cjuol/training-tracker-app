<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AssignedMesocycle;
use App\Entity\User;
use App\Form\AssignedMesocycleType;
use App\Repository\AssignedMesocycleRepository;
use App\Service\AssignmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/assignments')]
#[IsGranted('ROLE_ENTRENADOR')]
class AssignmentController extends AbstractController
{
    #[Route('', name: 'assignment_index', methods: ['GET'])]
    public function index(AssignedMesocycleRepository $repo): Response
    {
        /** @var User $coach */
        $coach = $this->getUser();

        $assignments = $repo->findByCoach($coach);

        return $this->render('assignment/index.html.twig', [
            'assignments' => $assignments,
        ]);
    }

    #[Route('/new', name: 'assignment_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        AssignmentService $assignmentService,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();

        $form = $this->createForm(AssignedMesocycleType::class, null, [
            'coach' => $coach,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var AssignedMesocycle $data */
            $data = $form->getData();

            try {
                $assignmentService->assign(
                    $coach,
                    $data->getAthlete(),
                    $data->getMesocycle(),
                    $data->getStartDate(),
                    $data->getEndDate(),
                );

                $this->addFlash('success', 'Mesociclo asignado correctamente.');

                return $this->redirectToRoute('assignment_index');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('assignment/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'assignment_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        AssignedMesocycle $assignment,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var User $coach */
        $coach = $this->getUser();

        // Only the coach who created the assignment may delete it
        if ($assignment->getAssignedBy()->getId() !== $coach->getId()) {
            throw $this->createAccessDeniedException('No tienes permiso para eliminar esta asignación.');
        }

        if ($this->isCsrfTokenValid('delete-assignment-'.$assignment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($assignment);
            $entityManager->flush();

            $this->addFlash('success', 'Asignación eliminada correctamente.');
        } else {
            $this->addFlash('error', 'Token CSRF inválido.');
        }

        return $this->redirectToRoute('assignment_index');
    }
}
