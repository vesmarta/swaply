<?php

namespace App\Entity;

use App\Enum\CashDirection;
use App\Enum\TradeOfferStatus;
use App\Repository\TradeOfferRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TradeOfferRepository::class)]
#[ORM\Index(name: 'IDX_OFFER_LISTING_STATUS', columns: ['listing_id', 'status'])]
class TradeOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Listing $listing = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $receiver = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Listing $offeredListing = null;

    #[ORM\Column(length: 20, enumType: CashDirection::class)]
    private CashDirection $cashDirection = CashDirection::NONE;

    #[ORM\Column]
    private int $cashAmountMillimes = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $message = null;

    #[ORM\Column(length: 20, enumType: TradeOfferStatus::class)]
    private TradeOfferStatus $status = TradeOfferStatus::PENDING;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $proposedBy = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TradeOffer $parent = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $requesterConfirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $ownerConfirmedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+7 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): ?Listing
    {
        return $this->listing;
    }

    public function setListing(?Listing $listing): static
    {
        $this->listing = $listing;

        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getReceiver(): ?User
    {
        return $this->receiver;
    }

    public function setReceiver(?User $receiver): static
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getOfferedListing(): ?Listing
    {
        return $this->offeredListing;
    }

    public function setOfferedListing(?Listing $offeredListing): static
    {
        $this->offeredListing = $offeredListing;

        return $this;
    }

    public function getCashDirection(): CashDirection
    {
        return $this->cashDirection;
    }

    public function setCashDirection(CashDirection $cashDirection): static
    {
        $this->cashDirection = $cashDirection;

        return $this;
    }

    public function getCashAmountMillimes(): int
    {
        return $this->cashAmountMillimes;
    }

    public function setCashAmountMillimes(int $cashAmountMillimes): static
    {
        $this->cashAmountMillimes = $cashAmountMillimes;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): TradeOfferStatus
    {
        return $this->status;
    }

    public function setStatus(TradeOfferStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getProposedBy(): ?User
    {
        return $this->proposedBy;
    }

    public function setProposedBy(?User $proposedBy): static
    {
        $this->proposedBy = $proposedBy;

        return $this;
    }

    public function getParent(): ?TradeOffer
    {
        return $this->parent;
    }

    public function setParent(?TradeOffer $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeImmutable $respondedAt): static
    {
        $this->respondedAt = $respondedAt;

        return $this;
    }

    public function getRequesterConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->requesterConfirmedAt;
    }

    public function setRequesterConfirmedAt(?\DateTimeImmutable $requesterConfirmedAt): static
    {
        $this->requesterConfirmedAt = $requesterConfirmedAt;

        return $this;
    }

    public function getOwnerConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->ownerConfirmedAt;
    }

    public function setOwnerConfirmedAt(?\DateTimeImmutable $ownerConfirmedAt): static
    {
        $this->ownerConfirmedAt = $ownerConfirmedAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return TradeOfferStatus::PENDING === $this->status
            && null !== $this->expiresAt
            && $this->expiresAt < new \DateTimeImmutable();
    }

    public function isAwaitingResponse(): bool
    {
        return TradeOfferStatus::PENDING === $this->status;
    }

    public function getResponder(): ?User
    {
        return $this->proposedBy === $this->sender ? $this->receiver : $this->sender;
    }

    public function bothPartiesConfirmed(): bool
    {
        return null !== $this->requesterConfirmedAt && null !== $this->ownerConfirmedAt;
    }

    /** The user who pays the cash, if any. */
    public function getPayer(): ?User
    {
        return match ($this->cashDirection) {
            CashDirection::REQUESTER_PAYS => $this->sender,
            CashDirection::OWNER_PAYS => $this->receiver,
            CashDirection::NONE => null,
        };
    }
}
