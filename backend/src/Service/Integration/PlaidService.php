<?php

namespace App\Service\Integration;

use App\Entity\IntegrationProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PlaidService
{
    private const API_URL_SANDBOX = 'https://sandbox.plaid.com';
    private const API_URL_DEVELOPMENT = 'https://development.plaid.com';
    private const API_URL_PRODUCTION = 'https://production.plaid.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private OAuth2Service $oauth2Service,
        private string $plaidClientId,
        private string $plaidSecret,
        private string $plaidEnv = 'sandbox', // sandbox, development, production
    ) {
    }

    private function getApiUrl(): string
    {
        return match ($this->plaidEnv) {
            'production' => self::API_URL_PRODUCTION,
            'development' => self::API_URL_DEVELOPMENT,
            default => self::API_URL_SANDBOX,
        };
    }

    /**
     * Create a Link token for Plaid Link UI
     */
    public function createLinkToken(IntegrationProvider $integration, string $userId): array
    {
        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/link/token/create', [
            'json' => [
                'client_id' => $this->plaidClientId,
                'secret' => $this->plaidSecret,
                'user' => [
                    'client_user_id' => $userId,
                ],
                'client_name' => 'Pourcha',
                'products' => ['transactions', 'auth'],
                'country_codes' => ['US', 'CA', 'GB', 'AU'],
                'language' => 'en',
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Exchange public token for access token
     */
    public function exchangePublicToken(IntegrationProvider $integration, string $publicToken): array
    {
        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/item/public_token/exchange', [
            'json' => [
                'client_id' => $this->plaidClientId,
                'secret' => $this->plaidSecret,
                'public_token' => $publicToken,
            ],
        ]);

        $data = $response->toArray();

        // Store access token
        $integration->setAccessToken($data['access_token']);
        $integration->setStatus(IntegrationProvider::STATUS_CONNECTED);
        $integration->setConnectedAt(new \DateTimeImmutable());

        // Store item_id in metadata
        $metadata = $integration->getMetadata() ?? [];
        $metadata['item_id'] = $data['item_id'];
        $integration->setMetadata($metadata);

        $this->entityManager->flush();

        return $data;
    }

    /**
     * Get account information
     */
    public function getAccounts(IntegrationProvider $integration): array
    {
        if (!$integration->getAccessToken()) {
            throw new \RuntimeException('Integration not connected');
        }

        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/accounts/get', [
            'json' => [
                'client_id' => $this->plaidClientId,
                'secret' => $this->plaidSecret,
                'access_token' => $integration->getAccessToken(),
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Get account balances
     */
    public function getBalances(IntegrationProvider $integration): array
    {
        if (!$integration->getAccessToken()) {
            throw new \RuntimeException('Integration not connected');
        }

        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/accounts/balance/get', [
            'json' => [
                'client_id' => $this->plaidClientId,
                'secret' => $this->plaidSecret,
                'access_token' => $integration->getAccessToken(),
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Get transactions
     */
    public function getTransactions(
        IntegrationProvider $integration,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        if (!$integration->getAccessToken()) {
            throw new \RuntimeException('Integration not connected');
        }

        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/transactions/get', [
            'json' => [
                'client_id' => $this->plaidClientId,
                'secret' => $this->plaidSecret,
                'access_token' => $integration->getAccessToken(),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Get Auth information (account and routing numbers for ACH)
     */
    public function getAuthInfo(IntegrationProvider $integration): array
    {
        if (!$integration->getAccessToken()) {
            throw new \RuntimeException('Integration not connected');
        }

        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/auth/get', [
            'json' => [
                'client_id' => $this->plaidClientId,
                'secret' => $this->plaidSecret,
                'access_token' => $integration->getAccessToken(),
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Initiate ACH payment
     */
    public function initiatePayment(
        IntegrationProvider $integration,
        string $accountId,
        float $amount,
        string $reference
    ): array {
        if (!$integration->getAccessToken()) {
            throw new \RuntimeException('Integration not connected');
        }

        // This requires Plaid Payment Initiation product
        $response = $this->httpClient->request('POST', $this->getApiUrl() . '/payment_initiation/payment/create', [
            'json' => [
                'client_id' => $this->plaidClientId,
                'secret' => $this->plaidSecret,
                'recipient_id' => $accountId,
                'reference' => $reference,
                'amount' => [
                    'currency' => 'USD',
                    'value' => $amount,
                ],
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Remove connection (invalidate access token)
     */
    public function removeConnection(IntegrationProvider $integration): void
    {
        if (!$integration->getAccessToken()) {
            return;
        }

        try {
            $this->httpClient->request('POST', $this->getApiUrl() . '/item/remove', [
                'json' => [
                    'client_id' => $this->plaidClientId,
                    'secret' => $this->plaidSecret,
                    'access_token' => $integration->getAccessToken(),
                ],
            ]);
        } catch (\Exception $e) {
            // Continue even if API call fails
        }

        $integration->setAccessToken(null);
        $integration->setStatus(IntegrationProvider::STATUS_DISCONNECTED);
        $this->entityManager->flush();
    }
}
