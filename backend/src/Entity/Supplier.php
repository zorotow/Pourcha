<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\SupplierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table(name: 'suppliers')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_USER')"),
        new Put(security: "is_granted('ROLE_USER')"),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['supplier:read']],
    denormalizationContext: ['groups' => ['supplier:write']]
)]
class Supplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['supplier:read', 'catalog:read', 'cart:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['supplier:read', 'supplier:write', 'catalog:read', 'cart:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $code = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $logoUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $website = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $address = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?string $locationCode = null;

    #[ORM\Column(length: 50)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private string $type = 'standard'; // standard, store, pickup_only

    #[ORM\Column]
    #[Groups(['supplier:read', 'supplier:write'])]
    private bool $isActive = true;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['supplier:read'])]
    private ?Tenant $tenant = null;

    #[ORM\OneToMany(mappedBy: 'supplier', targetEntity: CatalogItem::class)]
    private Collection $catalogItems;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['supplier:read', 'supplier:write'])]
    private ?array $metadata = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['supplier:read'])]
    private ?string $externalId = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['supplier:read'])]
    private ?string $externalSource = null; // xero, quickbooks, sap

    #[ORM\Column]
    #[Groups(['supplier:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['supplier:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->catalogItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
        $this->type = 'standard';
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): static { $this->code = $code; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getLogoUrl(): ?string { return $this->logoUrl; }
    public function setLogoUrl(?string $logoUrl): static { $this->logoUrl = $logoUrl; return $this; }
    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): static { $this->website = $website; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }
    public function getLocationCode(): ?string { return $this->locationCode; }
    public function setLocationCode(?string $locationCode): static { $this->locationCode = $locationCode; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }
    public function getCatalogItems(): Collection { return $this->catalogItems; }
    public function addCatalogItem(CatalogItem $item): static {
        if (!$this->catalogItems->contains($item)) {
            $this->catalogItems->add($item);
            $item->setSupplier($this);
        }
        return $this;
    }
    public function removeCatalogItem(CatalogItem $item): static {
        if ($this->catalogItems->removeElement($item)) {
            if ($item->getSupplier() === $this) {
                $item->setSupplier(null);
            }
        }
        return $this;
    }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): static { $this->externalId = $externalId; return $this; }
    public function getExternalSource(): ?string { return $this->externalSource; }
    public function setExternalSource(?string $externalSource): static { $this->externalSource = $externalSource; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
