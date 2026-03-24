<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CoachAthlete;
use App\Repository\CoachAthleteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CoachAthleteRepository $coachAthleteRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->userRepository->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC']);
        $links = $this->coachAthleteRepository->findBy([], ['id' => 'ASC']);

        // Pre-filter coaches: users whose stored roles include ROLE_ENTRENADOR or ROLE_ADMIN
        $coaches = array_values(array_filter(
            $users,
            static fn ($u) => $u->isCoach()
        ));

        return $this->render('admin/index.html.twig', [
            'users'   => $users,
            'links'   => $links,
            'coaches' => $coaches,
        ]);
    }

    #[Route('/coach-athlete', name: 'create_link', methods: ['POST'])]
    public function createLink(Request $request): Response
    {
        $token = $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('create_link', $token)) {
            $this->addFlash('error', 'Token CSRF inválido.');

            return $this->redirectToRoute('admin_index');
        }

        $coachId   = (int) $request->request->get('coach_id');
        $athleteId = (int) $request->request->get('athlete_id');

        $coachUser = $this->userRepository->find($coachId);
        if (null === $coachUser) {
            $this->addFlash('error', 'El entrenador seleccionado no existe.');

            return $this->redirectToRoute('admin_index');
        }

        // Validate that the selected coach actually holds ROLE_ENTRENADOR (or higher)
        if (!$coachUser->isCoach()) {
            $this->addFlash('error', 'El usuario seleccionado no tiene el rol de entrenador.');

            return $this->redirectToRoute('admin_index');
        }

        $athleteUser = $this->userRepository->find($athleteId);
        if (null === $athleteUser) {
            $this->addFlash('error', 'El atleta seleccionado no existe.');

            return $this->redirectToRoute('admin_index');
        }

        // Check for duplicate
        $existing = $this->coachAthleteRepository->findOneBy(['coach' => $coachUser, 'athlete' => $athleteUser]);
        if (null !== $existing) {
            $this->addFlash('error', 'Este vínculo entrenador–atleta ya existe.');

            return $this->redirectToRoute('admin_index');
        }

        $link = new CoachAthlete();
        $link->setCoach($coachUser);
        $link->setAthlete($athleteUser);

        $this->em->persist($link);
        $this->em->flush();

        $this->addFlash('success', 'Vínculo creado correctamente.');

        return $this->redirectToRoute('admin_index');
    }

    #[Route('/coach-athlete/{id}/delete', name: 'delete_link', methods: ['POST'])]
    public function deleteLink(int $id, Request $request): Response
    {
        $token = $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('delete_link_'.$id, $token)) {
            $this->addFlash('error', 'Token CSRF inválido.');

            return $this->redirectToRoute('admin_index');
        }

        $link = $this->coachAthleteRepository->find($id);
        if (null === $link) {
            throw $this->createNotFoundException('Vínculo no encontrado.');
        }

        $this->em->remove($link);
        $this->em->flush();

        $this->addFlash('success', 'Vínculo eliminado correctamente.');

        return $this->redirectToRoute('admin_index');
    }
}
