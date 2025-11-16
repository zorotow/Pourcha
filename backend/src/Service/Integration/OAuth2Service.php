<?php

namespace App\Service\Integration;

use App\Entity\IntegrationProvider;
use App\Entity\Tenant;
use App\Repository\IntegrationProviderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuth2Service
{
    private array $providerConfigs = [
        IntegrationProvider::PROVIDER_XERO => [
            'auth_url' => 'https://login.xero.com/identity/connect/authorize',
            'token_url' => 'https://identity.xero.com/connect/token',
            'scopes' => 'openid profile email accounting.transactions accounting.contacts offline_access',
        ],
        IntegrationProvider::PROVIDER_QUICKBOOKS => [
            'auth_url' => 'https://appcenter.intuit.com/connect/oauth2',
            'token_url' => 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer',
            'scopes' => 'com.intuit.quickbooks.accounting',
        ],
        IntegrationProvider::PROVIDER_PLAID => [
            'auth_url' => null, // Plaid uses Link, not OAuth redirect
            'token_url' => 'https://production.plaid.com/item/public_token/exchange',
            'scopes' => null,
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private IntegrationProviderRepository $integrationProviderRepository,
        private HttpClientInterface $httpClient,
        private string $appUrl,
    ) {
    }

    /**
     * Generate OAuth authorization URL for a provider
     */
    public function getAuthorizationUrl(Tenant $tenant, string $provider, string $state): string
    {
        if (!isset($this->providerConfigs[$provider])) {
            throw new \InvalidArgumentException("Provider {$provider} not supported");
        }

        $config = $this->providerConfigs[$provider];

        if (!$config['auth_url']) {
            throw new \InvalidArgumentException("Provider {$provider} does not use OAuth redirect flow");
        }

        $clientId = $_ENV[strtoupper($provider) . '_CLIENT_ID'] ?? '';
        $redirectUri = $this->getRedirectUri($provider);

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $config['scopes'],
            'state' => $state,
        ];

        return $config['auth_url'] . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(Tenant $tenant, string $provider, string $code): IntegrationProvider
    {
        if (!isset($this->providerConfigs[$provider])) {
            throw new \InvalidArgumentException("Provider {$provider} not supported");
        }

        $config = $this->providerConfigs[$provider];
        $clientId = $_ENV[strtoupper($provider) . '_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV[strtoupper($provider) . '_CLIENT_SECRET'] ?? '';

        $response = $this->httpClient->request('POST', $config['token_url'], [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode("{$clientId}:{$clientSecret}"),
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->getRedirectUri($provider),
            ],
        ]);

        $data = $response->toArray();

        // Find or create integration provider
        $integration = $this->integrationProviderRepository->findByTenantAndProvider($tenant, $provider);
        if (!$integration) {
            $integration = new IntegrationProvider();
            $integration->setTenant($tenant);
            $integration->setProvider($provider);
            $this->entityManager->persist($integration);
        }

        // Update tokens
        $integration->setAccessToken($data['access_token']);
        $integration->setRefreshToken($data['refresh_token'] ?? null);

        if (isset($data['expires_in'])) {
            $expiresAt = new \DateTimeImmutable('+' . $data['expires_in'] . ' seconds');
            $integration->setTokenExpiresAt($expiresAt);
        }

        $integration->setStatus(IntegrationProvider::STATUS_CONNECTED);
        $integration->setConnectedAt(new \DateTimeImmutable());

        // Store additional metadata
        $metadata = [];
        if (isset($data['token_type'])) {
            $metadata['token_type'] = $data['token_type'];
        }
        $integration->setMetadata($metadata);

        $this->entityManager->flush();

        return $integration;
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(IntegrationProvider $integration): IntegrationProvider
    {
        $provider = $integration->getProvider();

        if (!isset($this->providerConfigs[$provider])) {
            throw new \InvalidArgumentException("Provider {$provider} not supported");
        }

        if (!$integration->getRefreshToken()) {
            throw new \RuntimeException("No refresh token available");
        }

        $config = $this->providerConfigs[$provider];
        $clientId = $_ENV[strtoupper($provider) . '_CLIENT_ID'] ?? '';
        $clientSecret = $_ENV[strtoupper($provider) . '_CLIENT_SECRET'] ?? '';

        try {
            $response = $this->httpClient->request('POST', $config['token_url'], [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode("{$clientId}:{$clientSecret}"),
                ],
                'body' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $integration->getRefreshToken(),
                ],
            ]);

            $data = $response->toArray();

            $integration->setAccessToken($data['access_token']);

            if (isset($data['refresh_token'])) {
                $integration->setRefreshToken($data['refresh_token']);
            }

            if (isset($data['expires_in'])) {
                $expiresAt = new \DateTimeImmutable('+' . $data['expires_in'] . ' seconds');
                $integration->setTokenExpiresAt($expiresAt);
            }

            $integration->setStatus(IntegrationProvider::STATUS_CONNECTED);
            $this->entityManager->flush();

            return $integration;
        } catch (\Exception $e) {
            $integration->setStatus(IntegrationProvider::STATUS_ERROR);
            $this->entityManager->flush();
            throw $e;
        }
    }

    /**
     * Get valid access token, refreshing if necessary
     */
    public function getValidAccessToken(IntegrationProvider $integration): string
    {
        if ($integration->isTokenExpired() && $integration->getRefreshToken()) {
            $this->refreshAccessToken($integration);
        }

        if (!$integration->getAccessToken()) {
            throw new \RuntimeException("No access token available");
        }

        return $integration->getAccessToken();
    }

    /**
     * Disconnect integration
     */
    public function disconnect(IntegrationProvider $integration): void
    {
        $integration->setStatus(IntegrationProvider::STATUS_DISCONNECTED);
        $integration->setAccessToken(null);
        $integration->setRefreshToken(null);
        $integration->setTokenExpiresAt(null);
        $integration->setTenantId(null);
        $this->entityManager->flush();
    }

    private function getRedirectUri(string $provider): string
    {
        return $this->appUrl . "/api/integrations/{$provider}/callback";
    }
}
