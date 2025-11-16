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

class XeroService
{
    private const API_URL = 'https://api.xero.com/api.xro/2.0';

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private OAuth2Service $oauth2Service,
        private SupplierRepository $supplierRepository,
        private ChartOfAccountsRepository $chartOfAccountsRepository,
    ) {
    }

    /**
     * Get Xero tenant/organization ID
     */
    public function getXeroTenantId(IntegrationProvider $integration): string
    {
        if ($integration->getTenantId()) {
            return $integration->getTenantId();
        }

        $accessToken = $this->oauth2Service->getValidAccessToken($integration);

        $response = $this->httpClient->request('GET', 'https://api.xero.com/connections', [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
        ]);

        $connections = $response->toArray();

        if (empty($connections)) {
            throw new \RuntimeException('No Xero organizations found');
        }

        $tenantId = $connections[0]['tenantId'];
        $integration->setTenantId($tenantId);
        $this->entityManager->flush();

        return $tenantId;
    }

    /**
     * Sync suppliers from Xero (Contacts with type SUPPLIER)
     */
    public function syncSuppliersFromXero(IntegrationProvider $integration): array
    {
        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $tenantId = $this->getXeroTenantId($integration);

        $response = $this->httpClient->request('GET', self::API_URL . '/Contacts', [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ],
            'query' => [
                'where' => 'IsSupplier==true',
            ],
        ]);

        $data = $response->toArray();
        $contacts = $data['Contacts'] ?? [];

        $syncedSuppliers = [];

        foreach ($contacts as $contact) {
            // Find existing supplier by external ID
            $supplier = $this->supplierRepository->findOneBy([
                'externalId' => $contact['ContactID'],
                'tenant' => $integration->getTenant(),
            ]);

            if (!$supplier) {
                $supplier = new Supplier();
                $supplier->setTenant($integration->getTenant());
                $supplier->setExternalId($contact['ContactID']);
                $supplier->setExternalSource('xero');
                $this->entityManager->persist($supplier);
            }

            $supplier->setName($contact['Name']);
            $supplier->setEmail($contact['EmailAddress'] ?? '');

            // Set address if available
            if (!empty($contact['Addresses'])) {
                $address = $contact['Addresses'][0];
                $addressText = implode(', ', array_filter([
                    $address['AddressLine1'] ?? '',
                    $address['AddressLine2'] ?? '',
                    $address['City'] ?? '',
                    $address['Region'] ?? '',
                    $address['PostalCode'] ?? '',
                    $address['Country'] ?? '',
                ]));
                $supplier->setAddress($addressText);
            }

            // Set phone
            if (!empty($contact['Phones'])) {
                $phone = $contact['Phones'][0];
                $supplier->setPhone($phone['PhoneNumber'] ?? '');
            }

            $syncedSuppliers[] = $supplier;
        }

        $this->entityManager->flush();

        $integration->setLastSyncAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $syncedSuppliers;
    }

    /**
     * Sync Chart of Accounts from Xero
     */
    public function syncChartOfAccountsFromXero(IntegrationProvider $integration): array
    {
        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $tenantId = $this->getXeroTenantId($integration);

        $response = $this->httpClient->request('GET', self::API_URL . '/Accounts', [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray();
        $accounts = $data['Accounts'] ?? [];

        $syncedAccounts = [];

        foreach ($accounts as $account) {
            // Find existing account by external ID
            $coa = $this->chartOfAccountsRepository->findOneBy([
                'externalId' => $account['AccountID'],
                'tenant' => $integration->getTenant(),
            ]);

            if (!$coa) {
                $coa = new ChartOfAccounts();
                $coa->setTenant($integration->getTenant());
                $coa->setExternalId($account['AccountID']);
                $coa->setExternalSource('xero');
                $this->entityManager->persist($coa);
            }

            // Map Xero account to our structure
            $coa->setGlAccount($account['Code']);
            $coa->setGlAccountName($account['Name']);
            $coa->setAccountType($account['Type'] ?? 'EXPENSE');

            // For simplicity, use the same code for cost center and fund
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
     * Push invoice to Xero
     */
    public function pushInvoiceToXero(IntegrationProvider $integration, Invoice $invoice): array
    {
        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $tenantId = $this->getXeroTenantId($integration);

        // Get supplier's Xero Contact ID
        $supplier = $invoice->getSupplier();
        if (!$supplier->getExternalId()) {
            throw new \RuntimeException("Supplier must be synced with Xero first");
        }

        $invoiceData = [
            'Type' => 'ACCPAY', // Accounts Payable (bill from supplier)
            'Contact' => [
                'ContactID' => $supplier->getExternalId(),
            ],
            'Date' => $invoice->getInvoiceDate() ? $invoice->getInvoiceDate()->format('Y-m-d') : date('Y-m-d'),
            'DueDate' => $invoice->getDueDate() ? $invoice->getDueDate()->format('Y-m-d') : date('Y-m-d', strtotime('+30 days')),
            'InvoiceNumber' => $invoice->getInvoiceNumber(),
            'Status' => 'DRAFT',
            'LineAmountTypes' => 'Exclusive',
            'LineItems' => [
                [
                    'Description' => $invoice->getDescription() ?? 'Invoice ' . $invoice->getInvoiceNumber(),
                    'Quantity' => 1,
                    'UnitAmount' => (float) $invoice->getAmount(),
                    'AccountCode' => '400', // Default expense account
                ],
            ],
        ];

        $response = $this->httpClient->request('POST', self::API_URL . '/Invoices', [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Xero-Tenant-Id' => $tenantId,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'Invoices' => [$invoiceData],
            ],
        ]);

        $result = $response->toArray();

        if (!empty($result['Invoices'])) {
            $xeroInvoice = $result['Invoices'][0];
            $invoice->setExternalId($xeroInvoice['InvoiceID']);
            $invoice->setExternalSource('xero');
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * Push requisition (as purchase order) to Xero
     */
    public function pushRequisitionToXero(IntegrationProvider $integration, Requisition $requisition): array
    {
        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $tenantId = $this->getXeroTenantId($integration);

        $lineItems = [];
        foreach ($requisition->getItems() as $item) {
            $supplier = $item->getSupplier();
            if (!$supplier || !$supplier->getExternalId()) {
                throw new \RuntimeException("All items must have suppliers synced with Xero");
            }

            $lineItems[] = [
                'Description' => $item->getDescription(),
                'Quantity' => $item->getQuantity(),
                'UnitAmount' => (float) $item->getUnitPrice(),
                'AccountCode' => '400', // Default expense account
            ];
        }

        // Group by first supplier (simplified - in real world, split by supplier)
        $firstSupplier = $requisition->getItems()->first()->getSupplier();

        $poData = [
            'Contact' => [
                'ContactID' => $firstSupplier->getExternalId(),
            ],
            'Date' => $requisition->getSubmittedAt() ? $requisition->getSubmittedAt()->format('Y-m-d') : date('Y-m-d'),
            'Status' => 'DRAFT',
            'LineAmountTypes' => 'Exclusive',
            'LineItems' => $lineItems,
        ];

        $response = $this->httpClient->request('POST', self::API_URL . '/PurchaseOrders', [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Xero-Tenant-Id' => $tenantId,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'PurchaseOrders' => [$poData],
            ],
        ]);

        $result = $response->toArray();

        if (!empty($result['PurchaseOrders'])) {
            $xeroPO = $result['PurchaseOrders'][0];
            $requisition->setExternalId($xeroPO['PurchaseOrderID']);
            $requisition->setExternalSource('xero');
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * Get payment information for an invoice from Xero
     */
    public function getInvoicePayments(IntegrationProvider $integration, Invoice $invoice): array
    {
        if (!$invoice->getExternalId()) {
            return [];
        }

        $accessToken = $this->oauth2Service->getValidAccessToken($integration);
        $tenantId = $this->getXeroTenantId($integration);

        $response = $this->httpClient->request('GET', self::API_URL . "/Invoices/{$invoice->getExternalId()}", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Xero-Tenant-Id' => $tenantId,
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray();
        $xeroInvoice = $data['Invoices'][0] ?? null;

        if (!$xeroInvoice) {
            return [];
        }

        return $xeroInvoice['Payments'] ?? [];
    }
}
