<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Read-only entity mapping to the activite table.
 * Owned by the activities module — we only READ from this table.
 * Do NOT create migrations or modify this table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activite')]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'idActivite', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'titre', length: 255)]
    private string $titre = '';

    #[ORM\Column(name: 'description', type: 'text')]
    private string $description = '';

    #[ORM\Column(name: 'lieu', length: 255)]
    private string $lieu = '';

    #[ORM\Column(name: 'dateActivite', type: 'datetime')]
    private ?\DateTime $dateActivite = null;

    #[ORM\Column(name: 'dureParJour', type: 'integer')]
    private int $dureParJour = 1;

    #[ORM\Column(name: 'prix', type: 'decimal', precision: 10, scale: 2)]
    private float $prix = 0;

    #[ORM\Column(name: 'idGuide', type: 'integer')]
    private int $idGuide = 0;

    #[ORM\Column(name: 'image', length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(name: 'statut', length: 20)]
    private string $statut = 'Actif';

    #[ORM\Column(name: 'placesDisponibles', type: 'integer')]
    private int $placesDisponibles = 0;

    #[ORM\Column(name: 'categorie', length: 100, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(name: 'dateCreation', type: 'datetime')]
    private ?\DateTime $dateCreation = null;

    public function getId(): ?int { return $this->id; }
    public function getTitre(): string { return $this->titre; }
    public function getDescription(): string { return $this->description; }
    public function getLieu(): string { return $this->lieu; }
    public function getDateActivite(): ?\DateTime { return $this->dateActivite; }
    public function getDureParJour(): int { return $this->dureParJour; }
    public function getPrix(): float { return $this->prix; }
    public function getIdGuide(): int { return $this->idGuide; }
    public function getImage(): ?string { return $this->image; }
    public function getStatut(): string { return $this->statut; }
    public function getPlacesDisponibles(): int { return $this->placesDisponibles; }
    public function getCategorie(): ?string { return $this->categorie; }
    public function getDateCreation(): ?\DateTime { return $this->dateCreation; }
}