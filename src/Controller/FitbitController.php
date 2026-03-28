<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Fitbit\FitbitOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {}

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
