<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Exercise;
use App\Entity\User;
use App\Form\ExerciseType;
use App\Repository\ExerciseRepository;
use App\Security\Voter\ExerciseVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/exercises')]
#[IsGranted('ROLE_ENTRENADOR')]
class ExerciseController extends AbstractController
{
    #[Route('', name: 'exercise_index', methods: ['GET'])]
    public function index(Request $request, ExerciseRepository $exerciseRepository): Response
    {
        $search = $request->query->get('q');
        $exercises = $exerciseRepository->findAllWithSearch($search);

        return $this->render('exercise/index.html.twig', [
            'exercises' => $exercises,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'exercise_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $exercise = new Exercise();
        $form = $this->createForm(ExerciseType::class, $exercise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $exercise->setCreatedBy($user);

            $entityManager->persist($exercise);
            $entityManager->flush();

            $this->addFlash('success', 'Ejercicio creado correctamente.');

            return $this->redirectToRoute('exercise_index');
        }

        return $this->render('exercise/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'exercise_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Exercise $exercise, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ExerciseVoter::EXERCISE_EDIT, $exercise);

        $form = $this->createForm(ExerciseType::class, $exercise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Ejercicio actualizado correctamente.');

            return $this->redirectToRoute('exercise_index');
        }

        return $this->render('exercise/edit.html.twig', [
            'exercise' => $exercise,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'exercise_delete', methods: ['POST'])]
    public function delete(Request $request, Exercise $exercise, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ExerciseVoter::EXERCISE_DELETE, $exercise);

        if ($this->isCsrfTokenValid('delete-exercise-'.$exercise->getId(), $request->request->get('_token'))) {
            $entityManager->remove($exercise);
            $entityManager->flush();

            $this->addFlash('success', 'Ejercicio eliminado correctamente.');
        } else {
            $this->addFlash('error', 'Token CSRF inválido.');
        }

        return $this->redirectToRoute('exercise_index');
    }
}
