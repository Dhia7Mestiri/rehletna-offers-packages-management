<?php

namespace App\Entity;

use App\Repository\VolRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VolRepository::class)]
class Vol
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'vol')]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Service $service = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $flightNumber = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $departureCity = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $arrivalCity = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $departureAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $arrivalAt = null;

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        if ($service && $service->getVol() !== $this) {
            $service->setVol($this);
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->service?->getId();
    }

    public function getFlightNumber(): ?string
    {
        return $this->flightNumber;
    }

    public function setFlightNumber(?string $flightNumber): static
    {
        $this->flightNumber = $flightNumber;
        return $this;
    }

    public function getDepartureCity(): ?string
    {
        return $this->departureCity;
    }

    public function setDepartureCity(?string $departureCity): static
    {
        $this->departureCity = $departureCity;
        return $this;
    }

    public function getArrivalCity(): ?string
    {
        return $this->arrivalCity;
    }

    public function setArrivalCity(?string $arrivalCity): static
    {
        $this->arrivalCity = $arrivalCity;
        return $this;
    }

    public function getDepartureAt(): ?\DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function setDepartureAt(?\DateTimeImmutable $departureAt): static
    {
        $this->departureAt = $departureAt;
        return $this;
    }

    public function getArrivalAt(): ?\DateTimeImmutable
    {
        return $this->arrivalAt;
    }

    public function setArrivalAt(?\DateTimeImmutable $arrivalAt): static
    {
        $this->arrivalAt = $arrivalAt;
        return $this;
    }
}