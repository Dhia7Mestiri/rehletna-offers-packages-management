<?php

namespace App\Entity;

use App\Repository\HotelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HotelRepository::class)]
class Hotel
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'hotel')]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Service $service = null;

    #[ORM\Column(nullable: true)]
    private ?int $stars = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $roomType = null;

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        if ($service && $service->getHotel() !== $this) {
            $service->setHotel($this);
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->service?->getId();
    }

    public function getStars(): ?int
    {
        return $this->stars;
    }

    public function setStars(?int $stars): static
    {
        $this->stars = $stars;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getRoomType(): ?string
    {
        return $this->roomType;
    }

    public function setRoomType(?string $roomType): static
    {
        $this->roomType = $roomType;
        return $this;
    }
}