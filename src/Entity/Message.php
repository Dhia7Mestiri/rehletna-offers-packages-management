<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $content = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\ManyToOne(inversedBy: 'sentMessages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\ManyToOne(inversedBy: 'receivedMessages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $receiver = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $replies;

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
        $this->replies = new ArrayCollection();
    }

    // Getters and setters...
    public function getId(): ?int { return $this->id; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }
    public function isIsRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): static 
    { 
        $this->isRead = $isRead;
        if ($isRead) {
            $this->readAt = new \DateTimeImmutable();
        }
        return $this; 
    }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function getSender(): ?User { return $this->sender; }
    public function setSender(?User $sender): static { $this->sender = $sender; return $this; }
    public function getReceiver(): ?User { return $this->receiver; }
    public function setReceiver(?User $receiver): static { $this->receiver = $receiver; return $this; }
    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): static { $this->parent = $parent; return $this; }
    public function getReplies(): Collection { return $this->replies; }
}