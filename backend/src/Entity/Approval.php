<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\ApprovalRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ApprovalRepository::class)]
#[ORM\Table(name: 'approvals')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER')"),
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Put(security: "is_granted('ROLE_USER')")
    ],
    normalizationContext: ['groups' => ['approval:read']],
    denormalizationContext: ['groups' => ['approval:write']]
)]
class Approval
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['approval:read', 'requisition:read', 'invoice:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'approvals')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Requisition $requisition = null;

    #[ORM\ManyToOne(inversedBy: 'approvals')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['approval:read', 'requisition:read', 'invoice:read'])]
    private ?User $approver = null;

    #[ORM\Column(length: 50)]
    #[Groups(['approval:read', 'requisition:read', 'invoice:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['approval:read', 'approval:write'])]
    private ?string $comments = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['approval:read'])]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['approval:read'])]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column]
    #[Groups(['approval:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    public function getRequisition(): ?Requisition { return $this->requisition; }
    public function setRequisition(?Requisition $requisition): static { $this->requisition = $requisition; return $this; }
    public function getInvoice(): ?Invoice { return $this->invoice; }
    public function setInvoice(?Invoice $invoice): static { $this->invoice = $invoice; return $this; }
    public function getApprover(): ?User { return $this->approver; }
    public function setApprover(?User $approver): static { $this->approver = $approver; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getComments(): ?string { return $this->comments; }
    public function setComments(?string $comments): static { $this->comments = $comments; return $this; }
    public function getApprovedAt(): ?\DateTimeImmutable { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static { $this->approvedAt = $approvedAt; return $this; }
    public function getRejectedAt(): ?\DateTimeImmutable { return $this->rejectedAt; }
    public function setRejectedAt(?\DateTimeImmutable $rejectedAt): static { $this->rejectedAt = $rejectedAt; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
