<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object.getUser() == user"),
        new GetCollection(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['subscription:read']],
    denormalizationContext: ['groups' => ['subscription:write']]
)]
class Subscription
{
    public const PLAN_FREE = 'free';
    public const PLAN_PRO = 'pro';
    public const PLAN_ENTERPRISE = 'enterprise';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_TRIAL = 'trial';

    public const GATEWAY_STRIPE = 'stripe';
    public const GATEWAY_PAYPAL = 'paypal';
    public const GATEWAY_CRYPTO = 'crypto';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['subscription:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'subscription', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Groups(['subscription:read', 'user:read'])]
    private string $plan = self::PLAN_FREE;

    #[ORM\Column(length: 50)]
    #[Groups(['subscription:read', 'user:read'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['subscription:read'])]
    private ?string $gateway = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['subscription:read'])]
    private ?string $gatewaySubscriptionId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['subscription:read', 'user:read'])]
    private ?\DateTimeImmutable $renewalDate = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['subscription:read'])]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\OneToMany(mappedBy: 'subscription', targetEntity: Payment::class)]
    #[Groups(['subscription:read'])]
    private Collection $payments;

    #[ORM\Column]
    #[Groups(['subscription:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['subscription:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->plan = self::PLAN_FREE;
        $this->status = self::STATUS_ACTIVE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getGateway(): ?string
    {
        return $this->gateway;
    }

    public function setGateway(?string $gateway): static
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function getGatewaySubscriptionId(): ?string
    {
        return $this->gatewaySubscriptionId;
    }

    public function setGatewaySubscriptionId(?string $gatewaySubscriptionId): static
    {
        $this->gatewaySubscriptionId = $gatewaySubscriptionId;
        return $this;
    }

    public function getRenewalDate(): ?\DateTimeImmutable
    {
        return $this->renewalDate;
    }

    public function setRenewalDate(?\DateTimeImmutable $renewalDate): static
    {
        $this->renewalDate = $renewalDate;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setSubscription($this);
        }
        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getSubscription() === $this) {
                $payment->setSubscription(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFree(): bool
    {
        return $this->plan === self::PLAN_FREE;
    }

    public function isPro(): bool
    {
        return $this->plan === self::PLAN_PRO;
    }

    public function isEnterprise(): bool
    {
        return $this->plan === self::PLAN_ENTERPRISE;
    }
}
