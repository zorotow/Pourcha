<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\CartItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'cart_items')]
#[ApiResource]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cart:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cart $cart = null;

    #[ORM\ManyToOne(targetEntity: CatalogItem::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?CatalogItem $catalogItem = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['cart:read', 'cart:write'])]
    private int $quantity = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['cart:read'])]
    private ?string $unitPrice = null;

    #[ORM\ManyToOne(targetEntity: ChartOfAccounts::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?ChartOfAccounts $chartOfAccounts = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $paymentTerms = null;

    #[ORM\Column]
    #[Groups(['cart:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->quantity = 1;
    }

    public function getSubtotal(): float
    {
        return ((float) $this->unitPrice) * $this->quantity;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getCart(): ?Cart { return $this->cart; }
    public function setCart(?Cart $cart): static { $this->cart = $cart; return $this; }
    public function getCatalogItem(): ?CatalogItem { return $this->catalogItem; }
    public function setCatalogItem(?CatalogItem $catalogItem): static {
        $this->catalogItem = $catalogItem;
        // Auto-populate unit price from catalog item
        if ($catalogItem) {
            $this->unitPrice = $catalogItem->getPrice();
        }
        return $this;
    }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }
    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }
    public function getChartOfAccounts(): ?ChartOfAccounts { return $this->chartOfAccounts; }
    public function setChartOfAccounts(?ChartOfAccounts $chartOfAccounts): static { $this->chartOfAccounts = $chartOfAccounts; return $this; }
    public function getPaymentTerms(): ?string { return $this->paymentTerms; }
    public function setPaymentTerms(?string $paymentTerms): static { $this->paymentTerms = $paymentTerms; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
