<?php

namespace App\Entity;

use App\Enum\ListingCondition;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Repository\ListingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ListingRepository::class)]
#[ORM\Index(name: 'IDX_LISTING_STATUS_CREATED', columns: ['status', 'created_at'])]
class Listing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $seller = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Please enter a title.')]
    #[Assert\Length(min: 5, max: 120, minMessage: 'The title must be at least {{ limit }} characters.', maxMessage: 'The title cannot exceed {{ limit }} characters.')]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Please describe your item.')]
    #[Assert\Length(min: 20, max: 3000, minMessage: 'The description must be at least {{ limit }} characters.', maxMessage: 'The description cannot exceed {{ limit }} characters.')]
    private ?string $description = null;

    #[ORM\Column(name: 'item_condition', length: 20, enumType: ListingCondition::class)]
    #[Assert\NotNull(message: 'Please select the condition.')]
    private ?ListingCondition $condition = null;

    #[ORM\Column(length: 10, enumType: ListingType::class)]
    #[Assert\NotNull(message: 'Please select a listing type.')]
    private ?ListingType $type = null;

    #[ORM\Column(nullable: true)]
    private ?int $priceMillimes = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Please select a governorate.')]
    private ?string $governorate = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 20, enumType: ListingStatus::class)]
    private ListingStatus $status = ListingStatus::PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column]
    private int $viewCount = 0;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, ListingImage> */
    #[ORM\OneToMany(targetEntity: ListingImage::class, mappedBy: 'listing', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending, 'id' => \SortDirection::Ascending])]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCondition(): ?ListingCondition
    {
        return $this->condition;
    }

    public function setCondition(ListingCondition $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    public function getType(): ?ListingType
    {
        return $this->type;
    }

    public function setType(ListingType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPriceMillimes(): ?int
    {
        return $this->priceMillimes;
    }

    public function setPriceMillimes(?int $priceMillimes): static
    {
        $this->priceMillimes = $priceMillimes;

        return $this;
    }

    public function getGovernorate(): ?string
    {
        return $this->governorate;
    }

    public function setGovernorate(string $governorate): static
    {
        $this->governorate = $governorate;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getStatus(): ListingStatus
    {
        return $this->status;
    }

    public function setStatus(ListingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): static
    {
        $this->viewCount = $viewCount;

        return $this;
    }

    public function incrementViewCount(): static
    {
        ++$this->viewCount;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ListingImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ListingImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setListing($this);
            $image->setPosition($this->images->count() - 1);
        }

        return $this;
    }

    public function removeImage(ListingImage $image): static
    {
        if ($this->images->removeElement($image) && $image->getListing() === $this) {
            $image->setListing(null);
        }

        return $this;
    }

    public function getCoverImage(): ?ListingImage
    {
        return $this->images->first() ?: null;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
