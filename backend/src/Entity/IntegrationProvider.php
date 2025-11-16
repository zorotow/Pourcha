<?php

namespace App\Entity;

use App\Repository\IntegrationProviderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: IntegrationProviderRepository::class)]
#[ORM\Table(name: 'integration_providers')]
class IntegrationProvider
{
    public const PROVIDER_XERO = 'xero';
    public const PROVIDER_QUICKBOOKS = 'quickbooks';
    public const PROVIDER_SAP = 'sap';
    public const PROVIDER_PLAID = 'plaid';

    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['integration:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tenant $tenant = null;

    #[ORM\Column(length: 50)]
    #[Groups(['integration:read'])]
    private ?string $provider = null;

    #[ORM\Column(length: 50)]
    #[Groups(['integration:read'])]
    private string $status = self::STATUS_DISCONNECTED;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $tenantId = null; // External tenant/organization ID

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['integration:read'])]
    private ?array $config = [];

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['integration:read'])]
    private ?array $metadata = [];

    #[ORM\Column(nullable: true)]
    #[Groups(['integration:read'])]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['integration:read'])]
    private ?\DateTimeImmutable $connectedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;
        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): static
    {
        $this->accessToken = $accessToken;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
        return $this;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): static
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;
        return $this;
    }

    public function getConnectedAt(): ?\DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function setConnectedAt(?\DateTimeImmutable $connectedAt): static
    {
        $this->connectedAt = $connectedAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isTokenExpired(): bool
    {
        if (!$this->tokenExpiresAt) {
            return true;
        }
        return $this->tokenExpiresAt < new \DateTimeImmutable();
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && !$this->isTokenExpired();
    }
}
