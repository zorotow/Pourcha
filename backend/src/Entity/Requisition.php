<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\RequisitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RequisitionRepository::class)]
#[ORM\Table(name: 'requisitions')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Put(security: "is_granted('ROLE_USER')")
    ],
    normalizationContext: ['groups' => ['requisition:read']],
    denormalizationContext: ['groups' => ['requisition:write']]
)]
class Requisition
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['requisition:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['requisition:read'])]
    private ?string $requisitionNumber = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['requisition:read'])]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?User $onBehalfOf = null;

    #[ORM\Column(length: 50)]
    #[Groups(['requisition:read'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\OneToMany(mappedBy: 'requisition', targetEntity: RequisitionItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['requisition:read'])]
    private Collection $items;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $internalNote = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $noteToSupplier = null;

    #[ORM\Column]
    #[Groups(['requisition:read', 'requisition:write'])]
    private bool $hidePrice = false;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $deliveryAddress = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $locationCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $attentionTo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $specialDeliveryInstructions = null;

    #[ORM\Column]
    #[Groups(['requisition:read', 'requisition:write'])]
    private bool $requiresCommodityApproval = false;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $prescribedCommodity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?string $hedgedRate = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['requisition:read', 'requisition:write'])]
    private ?array $attachments = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['requisition:read'])]
    private ?string $totalAmount = '0';

    #[ORM\Column(length: 3)]
    #[Groups(['requisition:read'])]
    private string $currency = 'AUD';

    #[ORM\OneToMany(mappedBy: 'requisition', targetEntity: Approval::class, cascade: ['persist', 'remove'])]
    #[Groups(['requisition:read'])]
    private Collection $approvals;

    #[ORM\Column(nullable: true)]
    #[Groups(['requisition:read'])]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['requisition:read'])]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column]
    #[Groups(['requisition:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['requisition:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->approvals = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_DRAFT;
        $this->currency = 'AUD';
        $this->totalAmount = '0';
        $this->hidePrice = false;
        $this->requiresCommodityApproval = false;
    }

    public function calculateTotal(): void
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        $this->totalAmount = (string) $total;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getRequisitionNumber(): ?string { return $this->requisitionNumber; }
    public function setRequisitionNumber(string $requisitionNumber): static { $this->requisitionNumber = $requisitionNumber; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }
    public function getOnBehalfOf(): ?User { return $this->onBehalfOf; }
    public function setOnBehalfOf(?User $onBehalfOf): static { $this->onBehalfOf = $onBehalfOf; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getItems(): Collection { return $this->items; }
    public function addItem(RequisitionItem $item): static {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setRequisition($this);
        }
        return $this;
    }
    public function removeItem(RequisitionItem $item): static {
        if ($this->items->removeElement($item)) {
            if ($item->getRequisition() === $this) {
                $item->setRequisition(null);
            }
        }
        return $this;
    }
    public function getInternalNote(): ?string { return $this->internalNote; }
    public function setInternalNote(?string $internalNote): static { $this->internalNote = $internalNote; return $this; }
    public function getNoteToSupplier(): ?string { return $this->noteToSupplier; }
    public function setNoteToSupplier(?string $noteToSupplier): static { $this->noteToSupplier = $noteToSupplier; return $this; }
    public function isHidePrice(): bool { return $this->hidePrice; }
    public function setHidePrice(bool $hidePrice): static { $this->hidePrice = $hidePrice; return $this; }
    public function getDeliveryAddress(): ?string { return $this->deliveryAddress; }
    public function setDeliveryAddress(?string $deliveryAddress): static { $this->deliveryAddress = $deliveryAddress; return $this; }
    public function getLocationCode(): ?string { return $this->locationCode; }
    public function setLocationCode(?string $locationCode): static { $this->locationCode = $locationCode; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }
    public function getAttentionTo(): ?string { return $this->attentionTo; }
    public function setAttentionTo(?string $attentionTo): static { $this->attentionTo = $attentionTo; return $this; }
    public function getSpecialDeliveryInstructions(): ?string { return $this->specialDeliveryInstructions; }
    public function setSpecialDeliveryInstructions(?string $specialDeliveryInstructions): static { $this->specialDeliveryInstructions = $specialDeliveryInstructions; return $this; }
    public function isRequiresCommodityApproval(): bool { return $this->requiresCommodityApproval; }
    public function setRequiresCommodityApproval(bool $requiresCommodityApproval): static { $this->requiresCommodityApproval = $requiresCommodityApproval; return $this; }
    public function getPrescribedCommodity(): ?string { return $this->prescribedCommodity; }
    public function setPrescribedCommodity(?string $prescribedCommodity): static { $this->prescribedCommodity = $prescribedCommodity; return $this; }
    public function getHedgedRate(): ?string { return $this->hedgedRate; }
    public function setHedgedRate(?string $hedgedRate): static { $this->hedgedRate = $hedgedRate; return $this; }
    public function getAttachments(): ?array { return $this->attachments; }
    public function setAttachments(?array $attachments): static { $this->attachments = $attachments; return $this; }
    public function getTotalAmount(): ?string { return $this->totalAmount; }
    public function setTotalAmount(string $totalAmount): static { $this->totalAmount = $totalAmount; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }
    public function getApprovals(): Collection { return $this->approvals; }
    public function addApproval(Approval $approval): static {
        if (!$this->approvals->contains($approval)) {
            $this->approvals->add($approval);
            $approval->setRequisition($this);
        }
        return $this;
    }
    public function removeApproval(Approval $approval): static {
        if ($this->approvals->removeElement($approval)) {
            if ($approval->getRequisition() === $this) {
                $approval->setRequisition(null);
            }
        }
        return $this;
    }
    public function getSubmittedAt(): ?\DateTimeImmutable { return $this->submittedAt; }
    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static { $this->submittedAt = $submittedAt; return $this; }
    public function getApprovedAt(): ?\DateTimeImmutable { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static { $this->approvedAt = $approvedAt; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
