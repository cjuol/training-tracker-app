<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\BodyMeasurement;
use App\Entity\User;
use App\Form\BodyMeasurementType;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\BodyMeasurementRepository;
use App\Repository\DailyStepsRepository;
use App\Repository\WorkoutLogRepository;
use App\Security\Voter\BodyMeasurementVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Image;

#[Route('/profile')]
class ProfileController extends AbstractController
{
    private const PER_PAGE = 10;
    private const UPLOAD_DIR = 'uploads/profile_pictures';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    // -------------------------------------------------------------------------
    // Athlete profile index
    // -------------------------------------------------------------------------

    #[Route('', name: 'profile_index', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(
        BodyMeasurementRepository $repo,
        AssignedMesocycleRepository $assignedRepo,
        DailyStepsRepository $dailyStepsRepo,
        WorkoutLogRepository $workoutLogRepo,
    ): Response {
        /** @var User $athlete */
        $athlete = $this->getUser();
        $measurements = $repo->findLast10ByAthlete($athlete);
        $total = $repo->countByAthlete($athlete);

        $activeAssignments = $assignedRepo->findActiveByAthlete($athlete);
        $activeMesocycle = $activeAssignments[0] ?? null;

        $latestMeasurement = $measurements[0] ?? null;

        $todayEntry = $dailyStepsRepo->findByUserAndDate($athlete, new \DateTimeImmutable('today'));
        $todaySteps = $todayEntry?->getSteps() ?? 0;
        $stepsTarget = $activeMesocycle?->getMesocycle()->getDailyStepsTarget();

        $recentHistory = $workoutLogRepo->findRecentByUser($athlete, 10);

        return $this->render('profile/index.html.twig', [
            'measurements' => $measurements,
            'total' => $total,
            'activeMesocycle' => $activeMesocycle,
            'latestMeasurement' => $latestMeasurement,
            'todaySteps' => $todaySteps,
            'stepsTarget' => $stepsTarget,
            'recentHistory' => $recentHistory,
        ]);
    }

    // -------------------------------------------------------------------------
    // Profile picture — show form
    // -------------------------------------------------------------------------

    #[Route('/picture', name: 'profile_picture_show', methods: ['GET'])]
    #[IsGranted('ROLE_ATLETA')]
    public function picture(): Response
    {
        $form = $this->createPictureForm();

        return $this->render('profile/picture.html.twig', [
            'form' => $form,
        ]);
    }

    // -------------------------------------------------------------------------
    // Profile picture — handle upload
    // -------------------------------------------------------------------------

    #[Route('/picture', name: 'profile_picture_upload', methods: ['POST'])]
    #[IsGranted('ROLE_ATLETA')]
    public function uploadPicture(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createPictureForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('profile/picture.html.twig', [
                'form' => $form,
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile */
        $uploadedFile = $form->get('picture')->getData();

        // Delete old file if one exists
        if ($user->getProfilePictureFilename() !== null) {
            $oldPath = $this->projectDir.'/public/'.self::UPLOAD_DIR.'/'.$user->getProfilePictureFilename();
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Generate UUID filename, preserving extension
        $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension();
        $newFilename = Uuid::v4()->toRfc4122().'.'.$extension;
        $uploadedFile->move($this->projectDir.'/public/'.self::UPLOAD_DIR, $newFilename);

        $user->setProfilePictureFilename($newFilename);
        $em->flush();

        $this->addFlash('success', 'Foto de perfil actualizada correctamente.');

        return $this->redirectToRoute('profile_picture_show');
    }

    // -------------------------------------------------------------------------
    // Profile picture — handle delete
    // -------------------------------------------------------------------------

    #[Route('/picture/delete', name: 'profile_picture_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ATLETA')]
    public function deletePicture(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('delete_profile_picture', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        if ($user->getProfilePictureFilename() !== null) {
            $filePath = $this->projectDir.'/public/'.self::UPLOAD_DIR.'/'.$user->getProfilePictureFilename();
            if (is_file($filePath)) {
                @unlink($filePath);
            }
            $user->setProfilePictureFilename(null);
            $em->flush();
        }

        $this->addFlash('success', 'Foto de perfil eliminada correctamente.');

        return $this->redirectToRoute('profile_picture_show');
    }

    // -------------------------------------------------------------------------
    // Create measurement
    // -------------------------------------------------------------------------

    #[Route('/measurements/new', name: 'profile_measurement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ATLETA')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $athlete */
        $athlete = $this->getUser();
        $measurement = new BodyMeasurement();

        $form = $this->createForm(BodyMeasurementType::class, $measurement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $measurement->setAthlete($athlete);
            $em->persist($measurement);
            $em->flush();

            $this->addFlash('success', 'Medición registrada correctamente.');

            return $this->redirectToRoute('profile_index');
        }

        return $this->render('profile/new.html.twig', [
            'form' => $form,
        ]);
    }

    // -------------------------------------------------------------------------
    // Edit measurement
    // -------------------------------------------------------------------------

    #[Route('/measurements/{id}/edit', name: 'profile_measurement_edit', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(
        int $id,
        Request $request,
        BodyMeasurementRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $measurement = $repo->find($id);

        if (!$measurement instanceof BodyMeasurement) {
            throw $this->createNotFoundException('Medición no encontrada.');
        }

        $this->denyAccessUnlessGranted(BodyMeasurementVoter::MEASUREMENT_EDIT, $measurement);

        $form = $this->createForm(BodyMeasurementType::class, $measurement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $measurement->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', 'Medición actualizada correctamente.');

            return $this->redirectToRoute('profile_index');
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'measurement' => $measurement,
        ]);
    }

    // -------------------------------------------------------------------------
    // Delete measurement
    // -------------------------------------------------------------------------

    #[Route('/measurements/{id}/delete', name: 'profile_measurement_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(
        int $id,
        Request $request,
        BodyMeasurementRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        $measurement = $repo->find($id);

        if (!$measurement instanceof BodyMeasurement) {
            throw $this->createNotFoundException('Medición no encontrada.');
        }

        $this->denyAccessUnlessGranted(BodyMeasurementVoter::MEASUREMENT_DELETE, $measurement);

        if (!$this->isCsrfTokenValid('delete_measurement_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $em->remove($measurement);
        $em->flush();

        $this->addFlash('success', 'Medición eliminada correctamente.');

        return $this->redirectToRoute('profile_index');
    }

    // -------------------------------------------------------------------------
    // JSON paginated list (for Stimulus controller)
    // -------------------------------------------------------------------------

    #[Route('/measurements/list', name: 'profile_measurement_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(Request $request, BodyMeasurementRepository $repo, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        /** @var User $athlete */
        $athlete = $this->getUser();
        $page = max(1, (int) $request->query->get('page', 1));
        $total = $repo->countByAthlete($athlete);
        $items = $repo->findByAthletePaginated($athlete, $page, self::PER_PAGE);

        $itemsData = array_map(static function (BodyMeasurement $measurement) use ($csrfTokenManager): array {
            return [
                'id' => $measurement->getId(),
                'measurement_date' => $measurement->getMeasurementDate()->format('Y-m-d'),
                'weight_kg' => $measurement->getWeightKg(),
                'chest_cm' => $measurement->getChestCm(),
                'waist_cm' => $measurement->getWaistCm(),
                'hips_cm' => $measurement->getHipsCm(),
                'arms_cm' => $measurement->getArmsCm(),
                'notes' => $measurement->getNotes(),
                'csrf_token' => $csrfTokenManager->getToken('delete_measurement_'.$measurement->getId())->getValue(),
            ];
        }, $items);

        return new JsonResponse([
            'items' => $itemsData,
            'total' => $total,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'hasMore' => ($page * self::PER_PAGE) < $total,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createPictureForm(): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder()
            ->add('picture', FileType::class, [
                'label' => 'Foto de perfil',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Sólo se permiten imágenes JPEG, PNG o WebP.',
                        maxSizeMessage: 'La imagen no puede superar los 2 MB.',
                    ),
                ],
            ])
            ->getForm();
    }
}
