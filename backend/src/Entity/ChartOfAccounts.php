<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ChartOfAccountsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChartOfAccountsRepository::class)]
#[ORM\Table(name: 'chart_of_accounts')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['coa:read']],
    denormalizationContext: ['groups' => ['coa:write']]
)]
class ChartOfAccounts
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['coa:read', 'cart:read', 'requisition:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Groups(['coa:read', 'coa:write', 'cart:read', 'requisition:read'])]
    private ?string $costCenter = null;

    #[ORM\Column(length: 255)]
    #[Groups(['coa:read', 'coa:write', 'cart:read', 'requisition:read'])]
    private ?string $costCenterName = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Groups(['coa:read', 'coa:write', 'cart:read', 'requisition:read'])]
    private ?string $fund = null;

    #[ORM\Column(length: 255)]
    #[Groups(['coa:read', 'coa:write', 'cart:read', 'requisition:read'])]
    private ?string $fundName = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Groups(['coa:read', 'coa:write', 'cart:read', 'requisition:read'])]
    private ?string $glAccount = null;

    #[ORM\Column(length: 255)]
    #[Groups(['coa:read', 'coa:write', 'cart:read', 'requisition:read'])]
    private ?string $glAccountName = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tenant $tenant = null;

    #[ORM\Column]
    #[Groups(['coa:read', 'coa:write'])]
    private bool $isActive = true;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['coa:read'])]
    private ?string $accountType = null; // EXPENSE, REVENUE, ASSET, LIABILITY

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['coa:read'])]
    private ?string $externalId = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['coa:read'])]
    private ?string $externalSource = null; // xero, quickbooks, sap

    #[ORM\Column]
    #[Groups(['coa:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['coa:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
    }

    public function getFullCode(): string
    {
        return sprintf('%s-%s-%s', $this->costCenter, $this->fund, $this->glAccount);
    }

    public function getFullName(): string
    {
        return sprintf(
            '%s %s / %s %s / %s %s',
            $this->costCenter,
            $this->costCenterName,
            $this->fund,
            $this->fundName,
            $this->glAccount,
            $this->glAccountName
        );
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getCostCenter(): ?string { return $this->costCenter; }
    public function setCostCenter(string $costCenter): static { $this->costCenter = $costCenter; return $this; }
    public function getCostCenterName(): ?string { return $this->costCenterName; }
    public function setCostCenterName(string $costCenterName): static { $this->costCenterName = $costCenterName; return $this; }
    public function getFund(): ?string { return $this->fund; }
    public function setFund(string $fund): static { $this->fund = $fund; return $this; }
    public function getFundName(): ?string { return $this->fundName; }
    public function setFundName(string $fundName): static { $this->fundName = $fundName; return $this; }
    public function getGlAccount(): ?string { return $this->glAccount; }
    public function setGlAccount(string $glAccount): static { $this->glAccount = $glAccount; return $this; }
    public function getGlAccountName(): ?string { return $this->glAccountName; }
    public function setGlAccountName(string $glAccountName): static { $this->glAccountName = $glAccountName; return $this; }
    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getAccountType(): ?string { return $this->accountType; }
    public function setAccountType(?string $accountType): static { $this->accountType = $accountType; return $this; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): static { $this->externalId = $externalId; return $this; }
    public function getExternalSource(): ?string { return $this->externalSource; }
    public function setExternalSource(?string $externalSource): static { $this->externalSource = $externalSource; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
