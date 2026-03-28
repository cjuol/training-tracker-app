<?php

declare(strict_types=1);

namespace App\Service\Fitbit;

use App\Entity\FitbitToken;
use App\Entity\User;
use App\Exception\FitbitTokenRevokedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FitbitOAuthService
{
    private const SCOPES = 'activity heartrate sleep cardio_fitness';
    private const TOKEN_URL = 'https://api.fitbit.com/oauth2/token';
    private const REVOKE_URL = 'https://api.fitbit.com/oauth2/revoke';
    private const AUTH_URL = 'https://www.fitbit.com/oauth2/authorize';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getAuthorizationUrl(): string
    {
        // Generate PKCE
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $state = bin2hex(random_bytes(16));

        $session = $this->requestStack->getSession();
        $session->set('fitbit_oauth_state', $state);
        $session->set('fitbit_code_verifier', $codeVerifier);

        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function handleCallback(User $user, string $code, string $returnedState): FitbitToken
    {
        $session = $this->requestStack->getSession();
        $expectedState = $session->get('fitbit_oauth_state');
        $codeVerifier = $session->get('fitbit_code_verifier');
        $session->remove('fitbit_oauth_state');
        $session->remove('fitbit_code_verifier');

        if (!$expectedState || !hash_equals($expectedState, $returnedState)) {
            throw new \RuntimeException('Invalid OAuth state parameter');
        }

        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
                'code_verifier' => $codeVerifier,
            ],
        ]);

        $data = $response->toArray();

        $token = $user->getFitbitToken() ?? new FitbitToken();
        $token->setUser($user);
        $token->setPlainAccessToken($data['access_token']);
        $token->setPlainRefreshToken($data['refresh_token']);
        $token->setExpiresAt(new \DateTimeImmutable('+'.$data['expires_in'].' seconds'));
        $token->setGrantedScopes(explode(' ', $data['scope'] ?? ''));
        $token->setIsValid(true);
        $token->setConnectedAt(new \DateTimeImmutable());

        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    public function refreshToken(FitbitToken $token): void
    {
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $token->getPlainRefreshToken(),
                ],
            ]);

            $data = $response->toArray();
            $token->setPlainAccessToken($data['access_token']);
            $token->setPlainRefreshToken($data['refresh_token']);
            $token->setExpiresAt(new \DateTimeImmutable('+'.$data['expires_in'].' seconds'));
            $token->setIsValid(true);
            $this->em->flush();
        } catch (\Throwable $e) {
            $token->setIsValid(false);
            $this->em->flush();
            throw new FitbitTokenRevokedException('Fitbit token refresh failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function revokeToken(FitbitToken $token): void
    {
        try {
            $this->httpClient->request('POST', self::REVOKE_URL, [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => ['token' => $token->getPlainAccessToken()],
            ]);
        } catch (\Throwable) {
            // Best-effort revocation — always delete locally
        }

        $this->em->remove($token);
        $this->em->flush();
    }
}
