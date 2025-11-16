<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\Table(name: 'carts')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object.getUser() == user"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Put(security: "is_granted('ROLE_USER') and object.getUser() == user"),
        new Delete(security: "is_granted('ROLE_USER') and object.getUser() == user")
    ],
    normalizationContext: ['groups' => ['cart:read']],
    denormalizationContext: ['groups' => ['cart:write']]
)]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cart:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['cart:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Groups(['cart:read'])]
    private string $status = 'active'; // active, submitted, cancelled

    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private Collection $items;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $internalNote = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $noteToSupplier = null;

    #[ORM\Column]
    #[Groups(['cart:read', 'cart:write'])]
    private bool $hidePrice = false;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $deliveryAddress = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $locationCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $attentionTo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $specialDeliveryInstructions = null;

    #[ORM\Column]
    #[Groups(['cart:read', 'cart:write'])]
    private bool $requiresCommodityApproval = false;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $prescribedCommodity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?string $hedgedRate = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['cart:read', 'cart:write'])]
    private ?array $attachments = null;

    #[ORM\Column]
    #[Groups(['cart:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['cart:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'active';
        $this->hidePrice = false;
        $this->requiresCommodityApproval = false;
    }

    public function getTotal(): float
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getItems(): Collection { return $this->items; }
    public function addItem(CartItem $item): static {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCart($this);
        }
        return $this;
    }
    public function removeItem(CartItem $item): static {
        if ($this->items->removeElement($item)) {
            if ($item->getCart() === $this) {
                $item->setCart(null);
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
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
