<?php

namespace App\Model;

class ServiceDetails
{
    public ?int $idService = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $price = null;
    public ?bool $isAvailable = null;
    public ?int $capacity = null;
    public ?int $agencyId = null;
    public ?string $kind = null;

    public ?string $flightNumber = null;
    public ?string $departureCity = null;
    public ?string $arrivalCity = null;
    public ?\DateTimeImmutable $departureAt = null;
    public ?\DateTimeImmutable $arrivalAt = null;

    public ?int $stars = null;
    public ?string $location = null;
    public ?string $roomType = null;
}