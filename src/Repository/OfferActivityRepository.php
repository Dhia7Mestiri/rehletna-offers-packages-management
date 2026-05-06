<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

/**
 * Read-only repository for activity recommendations.
 * Uses native SQL to avoid conflicts with the activities module owner.
 * Never writes to the activite table.
 */
class OfferActivityRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {}

    /**
     * Find activities matching the offer location.
     * Matches on lieu column using LIKE for partial matches.
     */
    public function findByLocation(string $location, int $limit = 6): array
    {
        if (empty(trim($location))) {
            return [];
        }

        // Extract first word of location for better matching
        // e.g. "Tunis, Tunisia" → search for "Tunis"
        $keyword = trim(explode(',', $location)[0]);

        $sql = "
            SELECT
                idActivite,
                titre,
                description,
                lieu,
                prix,
                image,
                categorie,
                statut,
                placesDisponibles,
                dateActivite
            FROM activite
            WHERE statut = 'Actif'
              AND (
                  lieu LIKE :keyword
                  OR lieu LIKE :location
              )
            ORDER BY placesDisponibles DESC
            LIMIT " . (int) $limit;

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('keyword',  '%' . $keyword . '%');
        $stmt->bindValue('location', '%' . $location . '%');

        return $stmt->executeQuery()->fetchAllAssociative();
    }
}