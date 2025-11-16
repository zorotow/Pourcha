<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RequisitionItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RequisitionItemRepository::class)]
#[ORM\Table(name: 'requisition_items')]
#[ApiResource]
class RequisitionItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['requisition:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Requisition $requisition = null;

    #[ORM\Column(length: 255)]
    #[Groups(['requisition:read'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[Groups(['requisition:read'])]
    private ?Supplier $supplier = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['requisition:read'])]
    private ?string $supplierPartNumber = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['requisition:read'])]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['requisition:read'])]
    private ?string $unitPrice = null;

    #[ORM\Column(length: 20)]
    #[Groups(['requisition:read'])]
    private string $unit = 'Each';

    #[ORM\ManyToOne(targetEntity: ChartOfAccounts::class)]
    #[Groups(['requisition:read'])]
    private ?ChartOfAccounts $chartOfAccounts = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['requisition:read'])]
    private ?string $paymentTerms = null;

    #[ORM\Column(length: 3)]
    #[Groups(['requisition:read'])]
    private string $currency = 'AUD';

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['requisition:read'])]
    private ?string $commodity = null;

    #[ORM\Column]
    #[Groups(['requisition:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->quantity = 1;
        $this->unit = 'Each';
        $this->currency = 'AUD';
    }

    public function getSubtotal(): float
    {
        return ((float) $this->unitPrice) * $this->quantity;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getRequisition(): ?Requisition { return $this->requisition; }
    public function setRequisition(?Requisition $requisition): static { $this->requisition = $requisition; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }
    public function getSupplier(): ?Supplier { return $this->supplier; }
    public function setSupplier(?Supplier $supplier): static { $this->supplier = $supplier; return $this; }
    public function getSupplierPartNumber(): ?string { return $this->supplierPartNumber; }
    public function setSupplierPartNumber(?string $supplierPartNumber): static { $this->supplierPartNumber = $supplierPartNumber; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }
    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $unit): static { $this->unit = $unit; return $this; }
    public function getChartOfAccounts(): ?ChartOfAccounts { return $this->chartOfAccounts; }
    public function setChartOfAccounts(?ChartOfAccounts $chartOfAccounts): static { $this->chartOfAccounts = $chartOfAccounts; return $this; }
    public function getPaymentTerms(): ?string { return $this->paymentTerms; }
    public function setPaymentTerms(?string $paymentTerms): static { $this->paymentTerms = $paymentTerms; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }
    public function getCommodity(): ?string { return $this->commodity; }
    public function setCommodity(?string $commodity): static { $this->commodity = $commodity; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
