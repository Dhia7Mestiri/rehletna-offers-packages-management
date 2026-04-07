<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $basePrice = null;

    #[ORM\Column]
    private ?bool $isAvailable = true;

    #[ORM\Column(nullable: true)]
    private ?int $capacity = null;

    #[ORM\Column(nullable: true)]
    private ?int $agencyId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToOne(mappedBy: 'service', cascade: ['persist', 'remove'])]
    private ?Hotel $hotel = null;

    #[ORM\OneToOne(mappedBy: 'service', cascade: ['persist', 'remove'])]
    private ?Vol $vol = null;

    /**
     * @var Collection<int, OfferService>
     */
    #[ORM\OneToMany(mappedBy: 'service', targetEntity: OfferService::class)]
    private Collection $offerServices;

    public function __construct()
    {
        $this->offerServices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = strtoupper($type);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(?string $basePrice): static
    {
        $this->basePrice = $basePrice;
        return $this;
    }

    public function isAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): static
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getAgencyId(): ?int
    {
        return $this->agencyId;
    }

    public function setAgencyId(?int $agencyId): static
    {
        $this->agencyId = $agencyId;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(?Hotel $hotel): static
    {
        if ($hotel && $hotel->getService() !== $this) {
            $hotel->setService($this);
        }

        $this->hotel = $hotel;
        return $this;
    }

    public function getVol(): ?Vol
    {
        return $this->vol;
    }

    public function setVol(?Vol $vol): static
    {
        if ($vol && $vol->getService() !== $this) {
            $vol->setService($this);
        }

        $this->vol = $vol;
        return $this;
    }

    /**
     * @return Collection<int, OfferService>
     */
    public function getOfferServices(): Collection
    {
        return $this->offerServices;
    }

    public function addOfferService(OfferService $offerService): static
    {
        if (!$this->offerServices->contains($offerService)) {
            $this->offerServices->add($offerService);
            $offerService->setService($this);
        }

        return $this;
    }

    public function removeOfferService(OfferService $offerService): static
    {
        if ($this->offerServices->removeElement($offerService)) {
            if ($offerService->getService() === $this) {
                $offerService->setService(null);
            }
        }

        return $this;
    }
}