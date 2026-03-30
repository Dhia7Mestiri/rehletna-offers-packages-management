<?php

namespace App\Entity;

use App\Repository\ProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfileRepository::class)]
#[ORM\Table(name: 'profile')]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'blob', nullable: true)]
    private $image = null;

    #[ORM\Column(length: 25)]
    private ?string $member_premium = null;

    #[ORM\Column(length: 25)]
    private ?string $language = null;

    #[ORM\Column]
    private ?int $coins = 0;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'profile')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function setImage($image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        if ($this->image === null) {
            return null;
        }
        return 'data:image/png;base64,' . base64_encode(stream_get_contents($this->image));
    }

    public function getMemberPremium(): ?string
    {
        return $this->member_premium;
    }

    public function setMemberPremium(string $member_premium): static
    {
        $this->member_premium = $member_premium;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getCoins(): ?int
    {
        return $this->coins;
    }

    public function setCoins(int $coins): static
    {
        $this->coins = $coins;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
