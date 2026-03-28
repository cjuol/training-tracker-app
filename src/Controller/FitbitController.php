<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\FitbitTokenRevokedException;
use App\Service\Fitbit\FitbitOAuthService;
use App\Service\Fitbit\FitbitSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/fitbit')]
class FitbitController extends AbstractController
{
    public function __construct(
        private readonly FitbitOAuthService $oauthService,
        private readonly FitbitSyncService $syncService,
    ) {}

    #[Route('/sync', name: 'fitbit_sync', methods: ['POST'])]
    public function sync(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('fitbit_sync', $request->request->get('_token'))) {
            return new JsonResponse(['synced' => false, 'error' => 'Token CSRF inválido.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $token = $user->getFitbitToken();

        if (!$token || !$token->isValid()) {
            return new JsonResponse(['synced' => false, 'error' => 'Token Fitbit no válido. Vuelve a conectar tu cuenta.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->syncService->syncUser($user, new \DateTimeImmutable('today'));
            $this->syncService->syncUser($user, new \DateTimeImmutable('yesterday'));

            return new JsonResponse(['synced' => true, 'message' => 'Sincronizados 2 días correctamente.']);
        } catch (FitbitTokenRevokedException $e) {
            return new JsonResponse(['synced' => false, 'error' => 'Token Fitbit revocado. Vuelve a conectar tu cuenta.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return new JsonResponse(['synced' => false, 'error' => 'Error durante la sincronización: ' . $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/connect', name: 'fitbit_connect', methods: ['GET'])]
    public function connect(): Response
    {
        return $this->redirect($this->oauthService->getAuthorizationUrl());
    }

    #[Route('/callback', name: 'fitbit_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        if ($request->query->has('error')) {
            $this->addFlash('error', 'La conexión con Fitbit fue denegada.');
            return $this->redirectToRoute('profile_index');
        }

        try {
            $this->oauthService->handleCallback(
                $this->getUser(),
                $request->query->get('code', ''),
                $request->query->get('state', ''),
            );
            $this->addFlash('success', '¡Fitbit conectado correctamente!');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', 'Error al conectar Fitbit: ' . $e->getMessage());
        }

        return $this->redirectToRoute('profile_index');
    }

    #[Route('/disconnect', name: 'fitbit_disconnect', methods: ['POST'])]
    public function disconnect(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('fitbit_disconnect', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF inválido.');
            return $this->redirectToRoute('profile_index');
        }

        $token = $this->getUser()->getFitbitToken();
        if ($token) {
            $this->oauthService->revokeToken($token);
            $this->addFlash('success', 'Fitbit desconectado. Tus datos sincronizados se conservan.');
        }

        return $this->redirectToRoute('profile_index');
    }
}
