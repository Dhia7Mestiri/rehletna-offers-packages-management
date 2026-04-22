<?php

namespace App\Entity;

use App\Repository\ActualiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActualiteRepository::class)]
#[ORM\Table(name: 'actualites')]
class Actualite
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'idActualite', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Offer::class)]
    #[ORM\JoinColumn(name: 'idOffre', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Offer $offer = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'idAgence', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $agence = null;

    #[ORM\Column(name: 'bannerUrl', length: 500, nullable: true)]
    private ?string $bannerUrl = null;

    #[ORM\Column(name: 'titre', length: 255)]
    private string $titre = '';

    #[ORM\Column(name: 'createdAt')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'isActive')]
    private bool $isActive = true;

    #[ORM\Column(name: 'endsAt', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(name: 'clickCount')]
    private int $clickCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getOffer(): ?Offer { return $this->offer; }
    public function setOffer(?Offer $offer): self { $this->offer = $offer; return $this; }

    public function getAgence(): ?User { return $this->agence; }
    public function setAgence(?User $agence): self { $this->agence = $agence; return $this; }

    public function getBannerUrl(): ?string { return $this->bannerUrl; }
    public function setBannerUrl(?string $bannerUrl): self { $this->bannerUrl = $bannerUrl; return $this; }

    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $endsAt): self { $this->endsAt = $endsAt; return $this; }

    public function getClickCount(): int { return $this->clickCount; }
    public function setClickCount(int $clickCount): self { $this->clickCount = $clickCount; return $this; }

    public function isCurrentlyActive(): bool
    {
        if (!$this->isActive) return false;
        if ($this->endsAt && $this->endsAt < new \DateTimeImmutable()) return false;
        return true;
    }
}