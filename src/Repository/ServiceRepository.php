<?php

namespace App\Repository;

use App\Entity\Service;
use App\Model\ServiceDetails;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findDetailsByOfferId(int $offerId): array
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.offerServices', 'os')
            ->leftJoin('s.hotel', 'h')
            ->leftJoin('s.vol', 'v')
            ->andWhere('os.offer = :offerId')
            ->setParameter('offerId', $offerId)
            ->orderBy('s.name', 'ASC');

        $services = $qb->getQuery()->getResult();

        $detailsList = [];

        foreach ($services as $service) {
            $details = new ServiceDetails();
            $details->idService = $service->getId();
            $details->name = $service->getName();
            $details->description = $service->getDescription();
            $details->price = $service->getBasePrice();
            $details->isAvailable = $service->isAvailable();
            $details->capacity = $service->getCapacity();
            $details->agencyId = $service->getAgencyId();
            $details->kind = $service->getType();

            if ($service->getVol()) {
                $details->flightNumber = $service->getVol()->getFlightNumber();
                $details->departureCity = $service->getVol()->getDepartureCity();
                $details->arrivalCity = $service->getVol()->getArrivalCity();
                $details->departureAt = $service->getVol()->getDepartureAt();
                $details->arrivalAt = $service->getVol()->getArrivalAt();
            }

            if ($service->getHotel()) {
                $details->stars = $service->getHotel()->getStars();
                $details->location = $service->getHotel()->getLocation();
                $details->roomType = $service->getHotel()->getRoomType();
            }

            $detailsList[] = $details;
        }

        return $detailsList;
    }


    public function findAllDetails(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.hotel', 'h')
            ->leftJoin('s.vol', 'v')
            ->orderBy('s.name', 'ASC');

        $services = $qb->getQuery()->getResult();

        $detailsList = [];

        foreach ($services as $service) {
            $details = new ServiceDetails();
            $details->idService = $service->getId();
            $details->name = $service->getName();
            $details->description = $service->getDescription();
            $details->price = $service->getBasePrice();
            $details->isAvailable = $service->isAvailable();
            $details->capacity = $service->getCapacity();
            $details->agencyId = $service->getAgencyId();
            $details->kind = $service->getType();

            if ($service->getVol()) {
                $details->flightNumber = $service->getVol()->getFlightNumber();
                $details->departureCity = $service->getVol()->getDepartureCity();
                $details->arrivalCity = $service->getVol()->getArrivalCity();
                $details->departureAt = $service->getVol()->getDepartureAt();
                $details->arrivalAt = $service->getVol()->getArrivalAt();
            }

            if ($service->getHotel()) {
                $details->stars = $service->getHotel()->getStars();
                $details->location = $service->getHotel()->getLocation();
                $details->roomType = $service->getHotel()->getRoomType();
            }

            $detailsList[] = $details;
        }

        return $detailsList;
    }

    
    
}