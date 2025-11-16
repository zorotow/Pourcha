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

/**
 * SAP Integration Service
 *
 * This service provides integration with SAP ERP systems via SAP OData APIs.
 * Supports SAP S/4HANA and SAP Business One.
 */
class SAPService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private SupplierRepository $supplierRepository,
        private ChartOfAccountsRepository $chartOfAccountsRepository,
    ) {
    }

    /**
     * Get SAP API base URL from integration config
     */
    private function getApiUrl(IntegrationProvider $integration): string
    {
        $config = $integration->getConfig() ?? [];

        if (!isset($config['api_url'])) {
            throw new \RuntimeException('SAP API URL not configured');
        }

        return rtrim($config['api_url'], '/');
    }

    /**
     * Get authentication headers
     */
    private function getAuthHeaders(IntegrationProvider $integration): array
    {
        $config = $integration->getConfig() ?? [];

        // SAP typically uses Basic Auth or OAuth 2.0
        if ($integration->getAccessToken()) {
            return [
                'Authorization' => 'Bearer ' . $integration->getAccessToken(),
                'Content-Type' => 'application/json',
            ];
        }

        // Basic Auth fallback
        if (isset($config['username']) && isset($config['password'])) {
            $credentials = base64_encode($config['username'] . ':' . $config['password']);
            return [
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/json',
            ];
        }

        throw new \RuntimeException('SAP authentication credentials not configured');
    }

    /**
     * Sync business partners (suppliers) from SAP
     */
    public function syncSuppliersFromSAP(IntegrationProvider $integration): array
    {
        $apiUrl = $this->getApiUrl($integration);
        $headers = $this->getAuthHeaders($integration);

        // SAP Business Partner OData endpoint
        $response = $this->httpClient->request('GET',
            "{$apiUrl}/sap/opu/odata/sap/API_BUSINESS_PARTNER/A_BusinessPartner",
            [
                'headers' => $headers,
                'query' => [
                    '$filter' => "BusinessPartnerCategory eq '2'", // 2 = Organization (vendors)
                    '$format' => 'json',
                ],
            ]
        );

        $data = $response->toArray();
        $businessPartners = $data['d']['results'] ?? [];

        $syncedSuppliers = [];

        foreach ($businessPartners as $bp) {
            // Find existing supplier by external ID
            $supplier = $this->supplierRepository->findOneBy([
                'externalId' => $bp['BusinessPartner'],
                'tenant' => $integration->getTenant(),
            ]);

            if (!$supplier) {
                $supplier = new Supplier();
                $supplier->setTenant($integration->getTenant());
                $supplier->setExternalId($bp['BusinessPartner']);
                $supplier->setExternalSource('sap');
                $this->entityManager->persist($supplier);
            }

            $supplier->setName($bp['BusinessPartnerFullName'] ?? $bp['BusinessPartner']);

            // Additional fields from business partner detail
            if (isset($bp['OrganizationBPName1'])) {
                $supplier->setName($bp['OrganizationBPName1']);
            }

            $syncedSuppliers[] = $supplier;
        }

        $this->entityManager->flush();

        $integration->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $syncedSuppliers;
    }

    /**
     * Sync GL Accounts from SAP
     */
    public function syncChartOfAccountsFromSAP(IntegrationProvider $integration): array
    {
        $apiUrl = $this->getApiUrl($integration);
        $headers = $this->getAuthHeaders($integration);

        // SAP GL Account OData endpoint
        $response = $this->httpClient->request('GET',
            "{$apiUrl}/sap/opu/odata/sap/API_GLACCOUNTINCHARTOFACCOUNTS_SRV/A_GLAccountInChartOfAccounts",
            [
                'headers' => $headers,
                'query' => [
                    '$format' => 'json',
                ],
            ]
        );

        $data = $response->toArray();
        $glAccounts = $data['d']['results'] ?? [];

        $syncedAccounts = [];

        foreach ($glAccounts as $account) {
            // Find existing account by external ID
            $coa = $this->chartOfAccountsRepository->findOneBy([
                'externalId' => $account['GLAccount'],
                'tenant' => $integration->getTenant(),
            ]);

            if (!$coa) {
                $coa = new ChartOfAccounts();
                $coa->setTenant($integration->getTenant());
                $coa->setExternalId($account['GLAccount']);
                $coa->setExternalSource('sap');
                $this->entityManager->persist($coa);
            }

            // Map SAP account to our structure
            $coa->setGlAccount($account['GLAccount']);
            $coa->setGlAccountName($account['GLAccountName'] ?? '');

            // For simplicity, use default values for cost center and fund
            if (!$coa->getCostCenter()) {
                $coa->setCostCenter($account['CostCenter'] ?? '000000');
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
     * Push purchase order to SAP
     */
    public function pushRequisitionToSAP(IntegrationProvider $integration, Requisition $requisition): array
    {
        $apiUrl = $this->getApiUrl($integration);
        $headers = $this->getAuthHeaders($integration);

        $lineItems = [];
        foreach ($requisition->getItems() as $index => $item) {
            $supplier = $item->getSupplier();
            if (!$supplier || !$supplier->getExternalId()) {
                throw new \RuntimeException("All items must have suppliers synced with SAP");
            }

            $lineItems[] = [
                'PurchaseOrderItem' => (string) ($index + 1),
                'Material' => '',
                'PurchaseOrderItemText' => $item->getDescription(),
                'OrderQuantity' => (string) $item->getQuantity(),
                'NetPriceAmount' => (string) $item->getUnitPrice(),
                'Currency' => 'AUD',
            ];
        }

        // Group by first supplier (simplified)
        $firstSupplier = $requisition->getItems()->first()->getSupplier();

        $poData = [
            'Supplier' => $firstSupplier->getExternalId(),
            'PurchaseOrderDate' => $requisition->getSubmittedAt() ? $requisition->getSubmittedAt()->format('Y-m-d') : date('Y-m-d'),
            'DocumentCurrency' => 'AUD',
            'PurchaseOrderItem' => $lineItems,
        ];

        $response = $this->httpClient->request('POST',
            "{$apiUrl}/sap/opu/odata/sap/API_PURCHASEORDER_PROCESS_SRV/A_PurchaseOrder",
            [
                'headers' => $headers,
                'json' => $poData,
            ]
        );

        $result = $response->toArray();

        if (isset($result['d']['PurchaseOrder'])) {
            $requisition->setExternalId($result['d']['PurchaseOrder']);
            $requisition->setExternalSource('sap');
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * Push supplier invoice to SAP
     */
    public function pushInvoiceToSAP(IntegrationProvider $integration, Invoice $invoice): array
    {
        $apiUrl = $this->getApiUrl($integration);
        $headers = $this->getAuthHeaders($integration);

        $supplier = $invoice->getSupplier();
        if (!$supplier->getExternalId()) {
            throw new \RuntimeException("Supplier must be synced with SAP first");
        }

        $invoiceData = [
            'SupplierInvoice' => $invoice->getInvoiceNumber(),
            'FiscalYear' => $invoice->getInvoiceDate() ? $invoice->getInvoiceDate()->format('Y') : date('Y'),
            'DocumentDate' => $invoice->getInvoiceDate() ? $invoice->getInvoiceDate()->format('Y-m-d') : date('Y-m-d'),
            'PostingDate' => date('Y-m-d'),
            'InvoicingParty' => $supplier->getExternalId(),
            'DocumentCurrency' => $invoice->getCurrency(),
            'InvoiceGrossAmount' => (string) $invoice->getAmount(),
        ];

        $response = $this->httpClient->request('POST',
            "{$apiUrl}/sap/opu/odata/sap/API_SUPPLIERINVOICE_PROCESS_SRV/A_SupplierInvoice",
            [
                'headers' => $headers,
                'json' => $invoiceData,
            ]
        );

        $result = $response->toArray();

        if (isset($result['d']['SupplierInvoice'])) {
            $invoice->setExternalId($result['d']['SupplierInvoice']);
            $invoice->setExternalSource('sap');
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * Test SAP connection
     */
    public function testConnection(IntegrationProvider $integration): bool
    {
        try {
            $apiUrl = $this->getApiUrl($integration);
            $headers = $this->getAuthHeaders($integration);

            // Simple test: fetch service metadata
            $response = $this->httpClient->request('GET',
                "{$apiUrl}/sap/opu/odata/sap/API_BUSINESS_PARTNER/\$metadata",
                [
                    'headers' => $headers,
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
