<?php

namespace App\Entity;

use App\Enum\PurchaseStatus;
use App\Repository\PurchaseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PurchaseRepository::class)]
#[ORM\Index(name: 'IDX_PURCHASE_LISTING_STATUS', columns: ['listing_id', 'status'])]
class Purchase
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
    private ?User $buyer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $seller = null;

    #[ORM\Column]
    private int $priceMillimes = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $message = null;

    #[ORM\Column(length: 20, enumType: PurchaseStatus::class)]
    private PurchaseStatus $status = PurchaseStatus::PENDING;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $buyerConfirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sellerConfirmedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getBuyer(): ?User
    {
        return $this->buyer;
    }

    public function setBuyer(?User $buyer): static
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function getSeller(): ?User
    {
        return $this->seller;
    }

    public function setSeller(?User $seller): static
    {
        $this->seller = $seller;

        return $this;
    }

    public function getPriceMillimes(): int
    {
        return $this->priceMillimes;
    }

    public function setPriceMillimes(int $priceMillimes): static
    {
        $this->priceMillimes = $priceMillimes;

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

    public function getStatus(): PurchaseStatus
    {
        return $this->status;
    }

    public function setStatus(PurchaseStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getBuyerConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->buyerConfirmedAt;
    }

    public function setBuyerConfirmedAt(?\DateTimeImmutable $buyerConfirmedAt): static
    {
        $this->buyerConfirmedAt = $buyerConfirmedAt;

        return $this;
    }

    public function getSellerConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->sellerConfirmedAt;
    }

    public function setSellerConfirmedAt(?\DateTimeImmutable $sellerConfirmedAt): static
    {
        $this->sellerConfirmedAt = $sellerConfirmedAt;

        return $this;
    }

    public function bothPartiesConfirmed(): bool
    {
        return null !== $this->buyerConfirmedAt && null !== $this->sellerConfirmedAt;
    }
}
