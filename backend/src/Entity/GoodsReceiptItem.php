<?php

namespace App\Entity;

use App\Repository\GoodsReceiptItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: GoodsReceiptItemRepository::class)]
#[ORM\Table(name: 'goods_receipt_items')]
class GoodsReceiptItem
{
    public const CONDITION_GOOD = 'good';
    public const CONDITION_DAMAGED = 'damaged';
    public const CONDITION_DEFECTIVE = 'defective';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['goods_receipt:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GoodsReceipt::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GoodsReceipt $goodsReceipt = null;

    #[ORM\ManyToOne(targetEntity: RequisitionItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['goods_receipt:read'])]
    private ?RequisitionItem $requisitionItem = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $orderedQuantity = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $receivedQuantity = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $acceptedQuantity = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $rejectedQuantity = '0';

    #[ORM\Column(length: 50)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private string $condition = self::CONDITION_GOOD;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['goods_receipt:read', 'goods_receipt:write'])]
    private ?array $qualityChecks = null;

    #[ORM\Column]
    #[Groups(['goods_receipt:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->condition = self::CONDITION_GOOD;
        $this->orderedQuantity = '0';
        $this->receivedQuantity = '0';
        $this->acceptedQuantity = '0';
        $this->rejectedQuantity = '0';
    }

    public function hasDiscrepancy(): bool
    {
        return bccomp($this->receivedQuantity, $this->orderedQuantity, 2) !== 0;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getGoodsReceipt(): ?GoodsReceipt { return $this->goodsReceipt; }
    public function setGoodsReceipt(?GoodsReceipt $goodsReceipt): static { $this->goodsReceipt = $goodsReceipt; return $this; }
    public function getRequisitionItem(): ?RequisitionItem { return $this->requisitionItem; }
    public function setRequisitionItem(?RequisitionItem $requisitionItem): static { $this->requisitionItem = $requisitionItem; return $this; }
    public function getOrderedQuantity(): ?string { return $this->orderedQuantity; }
    public function setOrderedQuantity(string $orderedQuantity): static { $this->orderedQuantity = $orderedQuantity; return $this; }
    public function getReceivedQuantity(): ?string { return $this->receivedQuantity; }
    public function setReceivedQuantity(string $receivedQuantity): static { $this->receivedQuantity = $receivedQuantity; return $this; }
    public function getAcceptedQuantity(): ?string { return $this->acceptedQuantity; }
    public function setAcceptedQuantity(string $acceptedQuantity): static { $this->acceptedQuantity = $acceptedQuantity; return $this; }
    public function getRejectedQuantity(): ?string { return $this->rejectedQuantity; }
    public function setRejectedQuantity(string $rejectedQuantity): static { $this->rejectedQuantity = $rejectedQuantity; return $this; }
    public function getCondition(): string { return $this->condition; }
    public function setCondition(string $condition): static { $this->condition = $condition; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    public function getQualityChecks(): ?array { return $this->qualityChecks; }
    public function setQualityChecks(?array $qualityChecks): static { $this->qualityChecks = $qualityChecks; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
