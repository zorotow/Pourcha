<?php

namespace App\Service\Integration;

use App\Entity\IntegrationProvider;
use App\Entity\Supplier;
use App\Entity\ChartOfAccounts;
use App\Entity\Invoice;
use App\Entity\Requisition;
use App\Repository\SupplierRepository;
use App\Repository\ChartOfAccountsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class QuickBooksService
{
    private const API_URL = 'https://quickbooks.api.intuit.com/v3/company';
    private const SANDBOX_URL = 'https://sandbox-quickbooks.api.intuit.com/v3/company';

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private OAuth2Service $oauth2Service,
        private SupplierRepository $supplierRepository,
        private ChartOfAccountsRepository $chartOfAccountsRepository,
        private bool $useSandbox = false,
    ) {
    }

    private function getApiUrl(): string
    {
        return $this->useSandbox ? self::SANDBOX_URL : self::API_URL;
    }

    /**
     * Get QuickBooks company/realm ID
     */
    public function getRealmId(IntegrationProvider $integration): string
    {
        if ($integration->getTenantId()) {
            return $integration->getTenantId();
        }

        // Realm ID should be stored during OAuth callback
        // For now, throw exception
        throw new \RuntimeException('QuickBooks Realm ID not found. Please reconnect integration.');
    }

    /**
     * Sync vendors (suppliers) from QuickBooks
     */
    public function syncSuppliersFromQuickBooks(IntegrationProvider $integration): array
    {
        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $realmId = $this->getRealmId($integration);

        $response = $this->httpClient->request('GET',
            "{$this->getApiUrl()}/{$realmId}/query",
            [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'query' => "SELECT * FROM Vendor MAXRESULTS 1000",
                ],
            ]
        );

        $data = $response->toArray();
        $vendors = $data['QueryResponse']['Vendor'] ?? [];

        $syncedSuppliers = [];

        foreach ($vendors as $vendor) {
            // Find existing supplier by external ID
            $supplier = $this->supplierRepository->findOneBy([
                'externalId' => $vendor['Id'],
                'tenant' => $integration->getTenant(),
            ]);

            if (!$supplier) {
                $supplier = new Supplier();
                $supplier->setTenant($integration->getTenant());
                $supplier->setExternalId($vendor['Id']);
                $supplier->setExternalSource('quickbooks');
                $this->entityManager->persist($supplier);
            }

            $supplier->setName($vendor['DisplayName']);
            $supplier->setEmail($vendor['PrimaryEmailAddr']['Address'] ?? '');

            // Set address if available
            if (isset($vendor['BillAddr'])) {
                $addr = $vendor['BillAddr'];
                $addressText = implode(', ', array_filter([
                    $addr['Line1'] ?? '',
                    $addr['City'] ?? '',
                    $addr['CountrySubDivisionCode'] ?? '',
                    $addr['PostalCode'] ?? '',
                    $addr['Country'] ?? '',
                ]));
                $supplier->setAddress($addressText);
            }

            // Set phone
            if (isset($vendor['PrimaryPhone'])) {
                $supplier->setPhone($vendor['PrimaryPhone']['FreeFormNumber'] ?? '');
            }

            // Set website
            if (isset($vendor['WebAddr'])) {
                $supplier->setWebsite($vendor['WebAddr']['URI'] ?? '');
            }

            $syncedSuppliers[] = $supplier;
        }

        $this->entityManager->flush();

        $integration->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $syncedSuppliers;
    }

    /**
     * Sync Chart of Accounts from QuickBooks
     */
    public function syncChartOfAccountsFromQuickBooks(IntegrationProvider $integration): array
    {
        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $realmId = $this->getRealmId($integration);

        $response = $this->httpClient->request('GET',
            "{$this->getApiUrl()}/{$realmId}/query",
            [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'query' => "SELECT * FROM Account MAXRESULTS 1000",
                ],
            ]
        );

        $data = $response->toArray();
        $accounts = $data['QueryResponse']['Account'] ?? [];

        $syncedAccounts = [];

        foreach ($accounts as $account) {
            // Only sync expense accounts
            if (!in_array($account['AccountType'], ['Expense', 'Other Expense', 'Cost of Goods Sold'])) {
                continue;
            }

            // Find existing account by external ID
            $coa = $this->chartOfAccountsRepository->findOneBy([
                'externalId' => $account['Id'],
                'tenant' => $integration->getTenant(),
            ]);

            if (!$coa) {
                $coa = new ChartOfAccounts();
                $coa->setTenant($integration->getTenant());
                $coa->setExternalId($account['Id']);
                $coa->setExternalSource('quickbooks');
                $this->entityManager->persist($coa);
            }

            // Map QuickBooks account to our structure
            $coa->setGlAccount($account['AcctNum'] ?? $account['Id']);
            $coa->setGlAccountName($account['Name']);
            $coa->setAccountType($account['AccountType']);

            // For simplicity, use default values for cost center and fund
            if (!$coa->getCostCenter()) {
                $coa->setCostCenter('000000');
                $coa->setCostCenterName('Default');
            }
            if (!$coa->getFund()) {
                $coa->setFund('1000000');
                $coa->setFundName('General Fund');
            }

            $syncedAccounts[] = $coa;
        }

        $this->entityManager->flush();

        $integration->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $syncedAccounts;
    }

    /**
     * Push bill (invoice) to QuickBooks
     */
    public function pushInvoiceToQuickBooks(IntegrationProvider $integration, Invoice $invoice): array
    {
        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $realmId = $this->getRealmId($integration);

        // Get supplier's QuickBooks Vendor ID
        $supplier = $invoice->getSupplier();
        if (!$supplier->getExternalId()) {
            throw new \RuntimeException("Supplier must be synced with QuickBooks first");
        }

        $billData = [
            'VendorRef' => [
                'value' => $supplier->getExternalId(),
            ],
            'TxnDate' => $invoice->getInvoiceDate() ? $invoice->getInvoiceDate()->format('Y-m-d') : date('Y-m-d'),
            'DueDate' => $invoice->getDueDate() ? $invoice->getDueDate()->format('Y-m-d') : date('Y-m-d', strtotime('+30 days')),
            'DocNumber' => $invoice->getInvoiceNumber(),
            'Line' => [
                [
                    'DetailType' => 'AccountBasedExpenseLineDetail',
                    'Amount' => (float) $invoice->getAmount(),
                    'AccountBasedExpenseLineDetail' => [
                        'AccountRef' => [
                            'value' => '400', // Default expense account
                        ],
                    ],
                    'Description' => $invoice->getDescription() ?? 'Invoice ' . $invoice->getInvoiceNumber(),
                ],
            ],
        ];

        $response = $this->httpClient->request('POST',
            "{$this->getApiUrl()}/{$realmId}/bill",
            [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $billData,
            ]
        );

        $result = $response->toArray();

        if (isset($result['Bill']['Id'])) {
            $invoice->setExternalId($result['Bill']['Id']);
            $invoice->setExternalSource('quickbooks');
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * Push requisition (as purchase order) to QuickBooks
     */
    public function pushRequisitionToQuickBooks(IntegrationProvider $integration, Requisition $requisition): array
    {
        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $realmId = $this->getRealmId($integration);

        $lineItems = [];
        foreach ($requisition->getItems() as $item) {
            $supplier = $item->getSupplier();
            if (!$supplier || !$supplier->getExternalId()) {
                throw new \RuntimeException("All items must have suppliers synced with QuickBooks");
            }

            $lineItems[] = [
                'DetailType' => 'ItemBasedExpenseLineDetail',
                'Amount' => (float) $item->getSubtotal(),
                'ItemBasedExpenseLineDetail' => [
                    'Qty' => $item->getQuantity(),
                    'UnitPrice' => (float) $item->getUnitPrice(),
                ],
                'Description' => $item->getDescription(),
            ];
        }

        // Group by first supplier (simplified)
        $firstSupplier = $requisition->getItems()->first()->getSupplier();

        $poData = [
            'VendorRef' => [
                'value' => $firstSupplier->getExternalId(),
            ],
            'TxnDate' => $requisition->getSubmittedAt() ? $requisition->getSubmittedAt()->format('Y-m-d') : date('Y-m-d'),
            'Line' => $lineItems,
        ];

        $response = $this->httpClient->request('POST',
            "{$this->getApiUrl()}/{$realmId}/purchaseorder",
            [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $poData,
            ]
        );

        $result = $response->toArray();

        if (isset($result['PurchaseOrder']['Id'])) {
            $requisition->setExternalId($result['PurchaseOrder']['Id']);
            $requisition->setExternalSource('quickbooks');
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * Get payment information for a bill from QuickBooks
     */
    public function getBillPayments(IntegrationProvider $integration, Invoice $invoice): array
    {
        if (!$invoice->getExternalId()) {
            return [];
        }

        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $realmId = $this->getRealmId($integration);

        $response = $this->httpClient->request('GET',
            "{$this->getApiUrl()}/{$realmId}/bill/{$invoice->getExternalId()}",
            [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Accept' => 'application/json',
                ],
            ]
        );

        $data = $response->toArray();
        return $data['Bill'] ?? [];
    }
}
