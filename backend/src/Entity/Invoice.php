<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\InvoiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Put(security: "is_granted('ROLE_USER')")
    ],
    normalizationContext: ['groups' => ['invoice:read']],
    denormalizationContext: ['groups' => ['invoice:write']]
)]
class Invoice
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['invoice:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $invoiceNumber = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['invoice:read'])]
    private ?User $submittedBy = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?Supplier $supplier = null;

    #[ORM\ManyToOne(targetEntity: Requisition::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?Requisition $requisition = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private string $currency = 'AUD';

    #[ORM\Column(nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeImmutable $invoiceDate = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Groups(['invoice:read'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?array $attachments = null;

    #[ORM\ManyToOne(targetEntity: ChartOfAccounts::class)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?ChartOfAccounts $chartOfAccounts = null;

    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Approval::class, cascade: ['persist', 'remove'])]
    #[Groups(['invoice:read'])]
    private Collection $approvals;

    #[ORM\Column(nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $externalId = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $externalSource = null; // xero, quickbooks, sap

    #[ORM\Column]
    #[Groups(['invoice:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->approvals = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_DRAFT;
        $this->currency = 'AUD';
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getInvoiceNumber(): ?string { return $this->invoiceNumber; }
    public function setInvoiceNumber(string $invoiceNumber): static { $this->invoiceNumber = $invoiceNumber; return $this; }
    public function getSubmittedBy(): ?User { return $this->submittedBy; }
    public function setSubmittedBy(?User $submittedBy): static { $this->submittedBy = $submittedBy; return $this; }
    public function getSupplier(): ?Supplier { return $this->supplier; }
    public function setSupplier(?Supplier $supplier): static { $this->supplier = $supplier; return $this; }
    public function getRequisition(): ?Requisition { return $this->requisition; }
    public function setRequisition(?Requisition $requisition): static { $this->requisition = $requisition; return $this; }
    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(string $amount): static { $this->amount = $amount; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }
    public function getInvoiceDate(): ?\DateTimeImmutable { return $this->invoiceDate; }
    public function setInvoiceDate(?\DateTimeImmutable $invoiceDate): static { $this->invoiceDate = $invoiceDate; return $this; }
    public function getDueDate(): ?\DateTimeImmutable { return $this->dueDate; }
    public function setDueDate(?\DateTimeImmutable $dueDate): static { $this->dueDate = $dueDate; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getAttachments(): ?array { return $this->attachments; }
    public function setAttachments(?array $attachments): static { $this->attachments = $attachments; return $this; }
    public function getChartOfAccounts(): ?ChartOfAccounts { return $this->chartOfAccounts; }
    public function setChartOfAccounts(?ChartOfAccounts $chartOfAccounts): static { $this->chartOfAccounts = $chartOfAccounts; return $this; }
    public function getApprovals(): Collection { return $this->approvals; }
    public function addApproval(Approval $approval): static {
        if (!$this->approvals->contains($approval)) {
            $this->approvals->add($approval);
            $approval->setInvoice($this);
        }
        return $this;
    }
    public function removeApproval(Approval $approval): static {
        if ($this->approvals->removeElement($approval)) {
            if ($approval->getInvoice() === $this) {
                $approval->setInvoice(null);
            }
        }
        return $this;
    }
    public function getApprovedAt(): ?\DateTimeImmutable { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static { $this->approvedAt = $approvedAt; return $this; }
    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $paidAt): static { $this->paidAt = $paidAt; return $this; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): static { $this->externalId = $externalId; return $this; }
    public function getExternalSource(): ?string { return $this->externalSource; }
    public function setExternalSource(?string $externalSource): static { $this->externalSource = $externalSource; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
