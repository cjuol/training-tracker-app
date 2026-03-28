<?php

declare(strict_types=1);

namespace App\Service\Fitbit;

use App\Entity\FitbitToken;
use App\Exception\FitbitRateLimitException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FitbitApiClient
{
    private const BASE_URL = 'https://api.fitbit.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly FitbitOAuthService $oauthService,
    ) {
    }

    public function get(FitbitToken $token, string $path): array
    {
        if ($token->isExpiringSoon()) {
            $this->oauthService->refreshToken($token);
        }

        $response = $this->httpClient->request('GET', self::BASE_URL.$path, [
            'headers' => ['Authorization' => 'Bearer '.$token->getPlainAccessToken()],
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode === 401) {
            // Try refresh once
            $this->oauthService->refreshToken($token);
            $response = $this->httpClient->request('GET', self::BASE_URL.$path, [
                'headers' => ['Authorization' => 'Bearer '.$token->getPlainAccessToken()],
            ]);
            $statusCode = $response->getStatusCode();
        }

        if ($statusCode === 429) {
            throw new FitbitRateLimitException('Fitbit API rate limit exceeded');
        }

        if ($statusCode >= 400) {
            throw new \RuntimeException('Fitbit API error: HTTP '.$statusCode.' for '.$path);
        }

        return $response->toArray();
    }
}
