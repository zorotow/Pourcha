<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\CatalogItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CatalogItemRepository::class)]
#[ORM\Table(name: 'catalog_items')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Put(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['catalog:read']],
    denormalizationContext: ['groups' => ['catalog:write']]
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'supplier.name' => 'partial', 'category' => 'exact', 'brand' => 'partial'])]
#[ApiFilter(RangeFilter::class, properties: ['price'])]
class CatalogItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['catalog:read', 'cart:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['catalog:read', 'catalog:write', 'cart:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'catalogItems')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['catalog:read', 'catalog:write', 'cart:read'])]
    private ?Supplier $supplier = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['catalog:read', 'catalog:write', 'cart:read'])]
    private ?string $supplierPartNumber = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?string $sku = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['catalog:read', 'catalog:write', 'cart:read'])]
    private ?string $price = null;

    #[ORM\Column(length: 3)]
    #[Groups(['catalog:read', 'catalog:write', 'cart:read'])]
    private string $currency = 'AUD';

    #[ORM\Column(length: 20)]
    #[Groups(['catalog:read', 'catalog:write', 'cart:read'])]
    private string $unit = 'Each'; // Each, Box, Pack, etc.

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?string $category = null; // e.g., "Microscope Slides & Accessories"

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?string $commodity = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?string $brand = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?string $color = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?string $model = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?int $stockQuantity = null;

    #[ORM\Column]
    #[Groups(['catalog:read', 'catalog:write'])]
    private bool $isAvailable = true;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?string $imageUrl = null;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tenant $tenant = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['catalog:read', 'catalog:write'])]
    private ?array $specifications = null;

    #[ORM\Column]
    #[Groups(['catalog:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['catalog:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isAvailable = true;
        $this->currency = 'AUD';
        $this->unit = 'Each';
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getSupplier(): ?Supplier { return $this->supplier; }
    public function setSupplier(?Supplier $supplier): static { $this->supplier = $supplier; return $this; }
    public function getSupplierPartNumber(): ?string { return $this->supplierPartNumber; }
    public function setSupplierPartNumber(?string $supplierPartNumber): static { $this->supplierPartNumber = $supplierPartNumber; return $this; }
    public function getSku(): ?string { return $this->sku; }
    public function setSku(?string $sku): static { $this->sku = $sku; return $this; }
    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): static { $this->price = $price; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }
    public function getUnit(): string { return $this->unit; }
    public function setUnit(string $unit): static { $this->unit = $unit; return $this; }
    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): static { $this->category = $category; return $this; }
    public function getCommodity(): ?string { return $this->commodity; }
    public function setCommodity(?string $commodity): static { $this->commodity = $commodity; return $this; }
    public function getBrand(): ?string { return $this->brand; }
    public function setBrand(?string $brand): static { $this->brand = $brand; return $this; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): static { $this->color = $color; return $this; }
    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $model): static { $this->model = $model; return $this; }
    public function getStockQuantity(): ?int { return $this->stockQuantity; }
    public function setStockQuantity(?int $stockQuantity): static { $this->stockQuantity = $stockQuantity; return $this; }
    public function isAvailable(): bool { return $this->isAvailable; }
    public function setIsAvailable(bool $isAvailable): static { $this->isAvailable = $isAvailable; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }
    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): static { $this->tenant = $tenant; return $this; }
    public function getSpecifications(): ?array { return $this->specifications; }
    public function setSpecifications(?array $specifications): static { $this->specifications = $specifications; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}
