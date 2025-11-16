<?php

namespace App\Controller;

use App\Entity\IntegrationProvider;
use App\Repository\IntegrationProviderRepository;
use App\Repository\TenantRepository;
use App\Service\Integration\OAuth2Service;
use App\Service\Integration\XeroService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/api/integrations')]
class IntegrationController extends AbstractController
{
    public function __construct(
        private IntegrationProviderRepository $integrationProviderRepository,
        private TenantRepository $tenantRepository,
        private OAuth2Service $oauth2Service,
        private XeroService $xeroService,
    ) {
    }

    /**
     * List all integration providers for the tenant
     */
    #[Route('', name: 'api_integrations_list', methods: ['GET'])]
    public function listIntegrations(): JsonResponse
    {
        $user = $this->getUser();
        $tenant = $user->getTenant();

        $integrations = $this->integrationProviderRepository->findByTenant($tenant);

        // Add available providers that aren't connected
        $availableProviders = [
            IntegrationProvider::PROVIDER_XERO,
            IntegrationProvider::PROVIDER_QUICKBOOKS,
            IntegrationProvider::PROVIDER_SAP,
            IntegrationProvider::PROVIDER_PLAID,
        ];

        $connectedProviders = array_map(fn($i) => $i->getProvider(), $integrations);
        $disconnectedProviders = array_diff($availableProviders, $connectedProviders);

        $result = [];
        foreach ($integrations as $integration) {
            $result[] = [
                'id' => $integration->getId(),
                'provider' => $integration->getProvider(),
                'status' => $integration->getStatus(),
                'connected_at' => $integration->getConnectedAt()?->format('c'),
                'last_sync_at' => $integration->getLastSyncAt()?->format('c'),
                'metadata' => $integration->getMetadata(),
            ];
        }

        foreach ($disconnectedProviders as $provider) {
            $result[] = [
                'provider' => $provider,
                'status' => IntegrationProvider::STATUS_DISCONNECTED,
                'connected_at' => null,
                'last_sync_at' => null,
            ];
        }

        return $this->json(['integrations' => $result]);
    }

    /**
     * Initiate OAuth connection for a provider
     */
    #[Route('/{provider}/connect', name: 'api_integrations_connect', methods: ['POST'])]
    public function connectProvider(string $provider): JsonResponse
    {
        $user = $this->getUser();
        $tenant = $user->getTenant();

        try {
            // Generate state token for CSRF protection
            $state = bin2hex(random_bytes(16));
            // TODO: Store state in session or cache for verification

            $authUrl = $this->oauth2Service->getAuthorizationUrl($tenant, $provider, $state);

            return $this->json([
                'auth_url' => $authUrl,
                'state' => $state,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * OAuth callback handler
     */
    #[Route('/{provider}/callback', name: 'api_integrations_callback', methods: ['GET'])]
    public function handleCallback(string $provider, Request $request): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');

        if ($error) {
            return new RedirectResponse('/settings/integrations?error=' . urlencode($error));
        }

        if (!$code) {
            return new RedirectResponse('/settings/integrations?error=no_code');
        }

        try {
            // TODO: Verify state token

            // Get tenant from subdomain or session
            $subdomain = explode('.', $request->getHost())[0];
            $tenant = $this->tenantRepository->findOneBy(['subdomain' => $subdomain]);

            if (!$tenant) {
                return new RedirectResponse('/settings/integrations?error=tenant_not_found');
            }

            $integration = $this->oauth2Service->exchangeCodeForToken($tenant, $provider, $code);

            // For Xero, fetch the tenant ID
            if ($provider === IntegrationProvider::PROVIDER_XERO) {
                $this->xeroService->getXeroTenantId($integration);
            }

            return new RedirectResponse('/settings/integrations?success=1&provider=' . $provider);
        } catch (\Exception $e) {
            return new RedirectResponse('/settings/integrations?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Disconnect an integration
     */
    #[Route('/{provider}/disconnect', name: 'api_integrations_disconnect', methods: ['POST'])]
    public function disconnectProvider(string $provider): JsonResponse
    {
        $user = $this->getUser();
        $tenant = $user->getTenant();

        $integration = $this->integrationProviderRepository->findByTenantAndProvider($tenant, $provider);

        if (!$integration) {
            return $this->json(['error' => 'Integration not found'], Response::HTTP_NOT_FOUND);
        }

        $this->oauth2Service->disconnect($integration);

        return $this->json(['message' => 'Integration disconnected successfully']);
    }

    /**
     * Sync data from a connected integration
     */
    #[Route('/{provider}/sync', name: 'api_integrations_sync', methods: ['POST'])]
    public function syncProvider(string $provider): JsonResponse
    {
        $user = $this->getUser();
        $tenant = $user->getTenant();

        $integration = $this->integrationProviderRepository->findByTenantAndProvider($tenant, $provider);

        if (!$integration || !$integration->isConnected()) {
            return $this->json(['error' => 'Integration not connected'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = [];

            if ($provider === IntegrationProvider::PROVIDER_XERO) {
                $suppliers = $this->xeroService->syncSuppliersFromXero($integration);
                $accounts = $this->xeroService->syncChartOfAccountsFromXero($integration);

                $result = [
                    'suppliers_synced' => count($suppliers),
                    'accounts_synced' => count($accounts),
                ];
            }

            // TODO: Add sync for other providers

            return $this->json([
                'message' => 'Sync completed successfully',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Test integration connection
     */
    #[Route('/{provider}/test', name: 'api_integrations_test', methods: ['POST'])]
    public function testConnection(string $provider): JsonResponse
    {
        $user = $this->getUser();
        $tenant = $user->getTenant();

        $integration = $this->integrationProviderRepository->findByTenantAndProvider($tenant, $provider);

        if (!$integration) {
            return $this->json(['error' => 'Integration not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $accessToken = $this->oauth2Service->getValidAccessToken($integration);

            return $this->json([
                'status' => 'connected',
                'message' => 'Connection test successful',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
