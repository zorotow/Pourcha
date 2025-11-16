<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\GoodsReceiptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: GoodsReceiptRepository::class)]
#[ORM\Table(name: 'goods_receipts')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')")
    ],
    normalizationContext: ['groups' => ['goods_receipt:read']],
    denormalizationContext: ['groups' => ['goods_receipt:write']]
)]
class GoodsReceipt
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['goods_receipt:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['goods_receipt:read'])]
    private ?string $receiptNumber = null;

    #[ORM\ManyToOne(targetEntity: Requisition::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?Requisition $requisition = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['goods_receipt:read'])]
    private ?Supplier $supplier = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['goods_receipt:read'])]
    private ?User $receivedBy = null;

    #[ORM\Column(length: 50)]
    #[Groups(['goods_receipt:read'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\OneToMany(mappedBy: 'goodsReceipt', targetEntity: GoodsReceiptItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['goods_receipt:read'])]
    private Collection $items;

    #[ORM\Column]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?\DateTimeImmutable $receiptDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $deliveryNote = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $deliveryNoteNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $receivingLocation = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?array $qualityCheckResults = null;

    #[ORM\Column]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private bool $hasDiscrepancies = false;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['goods_receipt:read'])]
    private ?array $discrepancies = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['goods_receipt:read'])]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column]
    #[Groups(['goods_receipt:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['goods_receipt:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_DRAFT;
        $this->receiptDate = new \DateTimeImmutable();
        $this->hasDiscrepancies = false;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getReceiptNumber(): ?string { return $this->receiptNumber; }
    public function setReceiptNumber(string $receiptNumber): static { $this->receiptNumber = $receiptNumber; return $this; }
    public function getRequisition(): ?Requisition { return $this->requisition; }
    public function setRequisition(?Requisition $requisition): static { $this->requisition = $requisition; return $this; }
    public function getSupplier(): ?Supplier { return $this->supplier; }
    public function setSupplier(?Supplier $supplier): static { $this->supplier = $supplier; return $this; }
    public function getReceivedBy(): ?User { return $this->receivedBy; }
    public function setReceivedBy(?User $receivedBy): static { $this->receivedBy = $receivedBy; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getItems(): Collection { return $this->items; }
    public function addItem(GoodsReceiptItem $item): static {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setGoodsReceipt($this);
        }
        return $this;
    }
    public function removeItem(GoodsReceiptItem $item): static {
        if ($this->items->removeElement($item)) {
            if ($item->getGoodsReceipt() === $this) {
                $item->setGoodsReceipt(null);
            }
        }
        return $this;
    }
    public function getReceiptDate(): ?\DateTimeImmutable { return $this->receiptDate; }
    public function setReceiptDate(\DateTimeImmutable $receiptDate): static { $this->receiptDate = $receiptDate; return $this; }
    public function getDeliveryNote(): ?string { return $this->deliveryNote; }
    public function setDeliveryNote(?string $deliveryNote): static { $this->deliveryNote = $deliveryNote; return $this; }
    public function getDeliveryNoteNumber(): ?string { return $this->deliveryNoteNumber; }
    public function setDeliveryNoteNumber(?string $deliveryNoteNumber): static { $this->deliveryNoteNumber = $deliveryNoteNumber; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    public function getReceivingLocation(): ?string { return $this->receivingLocation; }
    public function setReceivingLocation(?string $receivingLocation): static { $this->receivingLocation = $receivingLocation; return $this; }
    public function getQualityCheckResults(): ?array { return $this->qualityCheckResults; }
    public function setQualityCheckResults(?array $qualityCheckResults): static { $this->qualityCheckResults = $qualityCheckResults; return $this; }
    public function hasDiscrepancies(): bool { return $this->hasDiscrepancies; }
    public function setHasDiscrepancies(bool $hasDiscrepancies): static { $this->hasDiscrepancies = $hasDiscrepancies; return $this; }
    public function getDiscrepancies(): ?array { return $this->discrepancies; }
    public function setDiscrepancies(?array $discrepancies): static { $this->discrepancies = $discrepancies; return $this; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static { $this->confirmedAt = $confirmedAt; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
