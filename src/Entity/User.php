<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 25)]
    private ?string $role = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 25)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 25)]
    #[Assert\NotBlank]
    private ?string $last_name = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'string', length: 25, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 0])]
    private ?bool $two_factor_enabled = false;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $two_factor_code = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $two_factor_expiry = null;

    #[ORM\Column(type: 'blob', nullable: true)]
    private $face_data = null;

    #[ORM\Column(type: 'blob', nullable: true)]
    private $fingerprint_data = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fingerprint_slot_id = null;

    // Relationships for other entities
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Shop::class)]
    private Collection $shops;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Todo::class)]
    private Collection $todos;

    #[ORM\OneToMany(mappedBy: 'sender', targetEntity: Message::class)]
    private Collection $sentMessages;

    #[ORM\OneToMany(mappedBy: 'receiver', targetEntity: Message::class)]
    private Collection $receivedMessages;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Purchase::class)]
    private Collection $purchases;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Profile::class, cascade: ['persist', 'remove'])]
    private ?Profile $profile = null;

    public function __construct()
    {
        $this->date = new \DateTime();
        $this->role = 'USER';
        $this->shops = new ArrayCollection();
        $this->todos = new ArrayCollection();
        $this->sentMessages = new ArrayCollection();
        $this->receivedMessages = new ArrayCollection();
        $this->purchases = new ArrayCollection();
    }

    // ========== REQUIRED SYMFONY INTERFACE METHODS ==========
    
    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        // Keep plain roles in DB but map to Symfony security roles.
        $storedRole = strtoupper((string) ($this->role ?? 'USER'));

        $roleMap = [
            'USER' => 'ROLE_USER',
            'GUIDER' => 'ROLE_GUIDE',
            'AGENCY' => 'ROLE_AGENCY',
            'ADMIN' => 'ROLE_ADMIN',
            // Backward compatibility if older rows still contain ROLE_* values.
            'ROLE_USER' => 'ROLE_USER',
            'ROLE_GUIDE' => 'ROLE_GUIDE',
            'ROLE_AGENCY' => 'ROLE_AGENCY',
            'ROLE_ADMIN' => 'ROLE_ADMIN',
        ];

        $primaryRole = $roleMap[$storedRole] ?? 'ROLE_USER';

        // Keep ROLE_USER available for base pages (e.g. mainpage) while preserving special roles.
        return array_values(array_unique([$primaryRole, 'ROLE_USER']));
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Clear sensitive data if any
        $this->two_factor_code = null;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    // ========== BASIC GETTERS/SETTERS ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $normalized = strtoupper((string) $role);

        $dbRoleMap = [
            'ROLE_USER' => 'USER',
            'ROLE_GUIDE' => 'GUIDER',
            'ROLE_AGENCY' => 'AGENCY',
            'ROLE_ADMIN' => 'ADMIN',
            'USER' => 'USER',
            'GUIDER' => 'GUIDER',
            'AGENCY' => 'AGENCY',
            'ADMIN' => 'ADMIN',
        ];

        $this->role = $dbRoleMap[$normalized] ?? 'USER';
        return $this;
    }

    /**
     * Alias for setRole to maintain compatibility
     */
    public function setRoles(array $roles): static
    {
        $this->setRole(!empty($roles) ? $roles[0] : 'USER');
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
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

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(string $last_name): static
    {
        $this->last_name = $last_name;
        return $this;
    }

    /**
     * Get full name (name + last_name)
     */
    public function getFullName(): string
    {
        return trim($this->name . ' ' . $this->last_name);
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isTwoFactorEnabled(): ?bool
    {
        return $this->two_factor_enabled;
    }

    public function setTwoFactorEnabled(?bool $two_factor_enabled): static
    {
        $this->two_factor_enabled = $two_factor_enabled;
        return $this;
    }

    public function getTwoFactorCode(): ?string
    {
        return $this->two_factor_code;
    }

    public function setTwoFactorCode(?string $two_factor_code): static
    {
        $this->two_factor_code = $two_factor_code;
        return $this;
    }

    public function getTwoFactorExpiry(): ?\DateTimeInterface
    {
        return $this->two_factor_expiry;
    }

    public function setTwoFactorExpiry(?\DateTimeInterface $two_factor_expiry): static
    {
        $this->two_factor_expiry = $two_factor_expiry;
        return $this;
    }

    public function getFaceData()
    {
        return $this->face_data;
    }

    public function setFaceData($face_data): static
    {
        $this->face_data = $face_data;
        return $this;
    }

    public function getFingerprintData()
    {
        return $this->fingerprint_data;
    }

    public function setFingerprintData($fingerprint_data): static
    {
        $this->fingerprint_data = $fingerprint_data;
        return $this;
    }

    public function getFingerprintSlotId(): ?int
    {
        return $this->fingerprint_slot_id;
    }

    public function setFingerprintSlotId(?int $fingerprint_slot_id): static
    {
        $this->fingerprint_slot_id = $fingerprint_slot_id;
        return $this;
    }

    // ========== COLLECTION GETTERS ==========

    public function getShops(): Collection
    {
        return $this->shops;
    }

    public function getTodos(): Collection
    {
        return $this->todos;
    }

    public function getSentMessages(): Collection
    {
        return $this->sentMessages;
    }

    public function getReceivedMessages(): Collection
    {
        return $this->receivedMessages;
    }

    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): static
    {
        $this->profile = $profile;
        return $this;
    }

    // ========== COLLECTION HELPER METHODS ==========

    public function addShop(Shop $shop): static
    {
        if (!$this->shops->contains($shop)) {
            $this->shops->add($shop);
            $shop->setUser($this);
        }
        return $this;
    }

    public function removeShop(Shop $shop): static
    {
        if ($this->shops->removeElement($shop)) {
            if ($shop->getUser() === $this) {
                $shop->setUser(null);
            }
        }
        return $this;
    }

    public function addTodo(Todo $todo): static
    {
        if (!$this->todos->contains($todo)) {
            $this->todos->add($todo);
            $todo->setUser($this);
        }
        return $this;
    }

    public function removeTodo(Todo $todo): static
    {
        if ($this->todos->removeElement($todo)) {
            if ($todo->getUser() === $this) {
                $todo->setUser(null);
            }
        }
        return $this;
    }

    public function addSentMessage(Message $message): static
    {
        if (!$this->sentMessages->contains($message)) {
            $this->sentMessages->add($message);
            $message->setSender($this);
        }
        return $this;
    }

    public function removeSentMessage(Message $message): static
    {
        if ($this->sentMessages->removeElement($message)) {
            if ($message->getSender() === $this) {
                $message->setSender(null);
            }
        }
        return $this;
    }

    public function addReceivedMessage(Message $message): static
    {
        if (!$this->receivedMessages->contains($message)) {
            $this->receivedMessages->add($message);
            $message->setReceiver($this);
        }
        return $this;
    }

    public function removeReceivedMessage(Message $message): static
    {
        if ($this->receivedMessages->removeElement($message)) {
            if ($message->getReceiver() === $this) {
                $message->setReceiver(null);
            }
        }
        return $this;
    }

    public function addPurchase(Purchase $purchase): static
    {
        if (!$this->purchases->contains($purchase)) {
            $this->purchases->add($purchase);
            $purchase->setUser($this);
        }
        return $this;
    }

    public function removePurchase(Purchase $purchase): static
    {
        if ($this->purchases->removeElement($purchase)) {
            if ($purchase->getUser() === $this) {
                $purchase->setUser(null);
            }
        }
        return $this;
    }

    // ========== STATISTICS METHODS ==========

    public function getShopsCount(): int
    {
        return $this->shops->count();
    }

    public function getTodosCount(): int
    {
        return $this->todos->count();
    }

    public function getCompletedTodosCount(): int
    {
        return $this->todos->filter(fn(Todo $todo) => $todo->isIsCompleted())->count();
    }

    public function getPendingTodosCount(): int
    {
        return $this->todos->filter(fn(Todo $todo) => !$todo->isIsCompleted())->count();
    }

    public function getAllMessages(): Collection
    {
        $messages = new ArrayCollection(
            array_merge(
                $this->sentMessages->toArray(),
                $this->receivedMessages->toArray()
            )
        );
        
        $iterator = $messages->getIterator();
        $iterator->uasort(fn(Message $a, Message $b) => 
            $b->getSentAt() <=> $a->getSentAt()
        );
        
        return new ArrayCollection(iterator_to_array($iterator));
    }

    public function getUnreadMessagesCount(): int
    {
        return $this->receivedMessages
            ->filter(fn(Message $message) => !$message->isIsRead())
            ->count();
    }

    public function getPurchasesCount(): int
    {
        return $this->purchases->count();
    }

    public function getTotalSpent(): float
    {
        $total = 0;
        foreach ($this->purchases as $purchase) {
            $total += floatval($purchase->getAmount()) * $purchase->getQuantity();
        }
        return $total;
    }

    public function getLastPurchaseDate(): ?\DateTimeImmutable
    {
        if ($this->purchases->isEmpty()) {
            return null;
        }
        
        $lastPurchase = $this->purchases->first();
        foreach ($this->purchases as $purchase) {
            if ($purchase->getPurchaseDate() > $lastPurchase->getPurchaseDate()) {
                $lastPurchase = $purchase;
            }
        }
        
        return $lastPurchase->getPurchaseDate();
    }

    // ========== UTILITY METHODS ==========

    public function getInitials(): string
    {
        $first = substr($this->name ?? '', 0, 1);
        $last = substr($this->last_name ?? '', 0, 1);
        return strtoupper($first . $last);
    }

    public function isProfileComplete(): bool
    {
        return !empty($this->name) 
            && !empty($this->last_name) 
            && !empty($this->email)
            && !empty($this->password);
    }

    public function getProfileCompletionPercentage(): int
    {
        $fields = [
            'name' => !empty($this->name),
            'last_name' => !empty($this->last_name),
            'email' => !empty($this->email),
            'phone' => !empty($this->phone),
            'address' => !empty($this->address),
        ];
        
        $completed = count(array_filter($fields));
        return (int) (($completed / count($fields)) * 100);
    }

    public function hasShops(): bool
    {
        return !$this->shops->isEmpty();
    }

    public function hasTodos(): bool
    {
        return !$this->todos->isEmpty();
    }

    public function hasMessages(): bool
    {
        return !$this->sentMessages->isEmpty() || !$this->receivedMessages->isEmpty();
    }

    public function hasPurchases(): bool
    {
        return !$this->purchases->isEmpty();
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_enabled === true;
    }

    public function isTwoFactorCodeValid(string $code): bool
    {
        return $this->two_factor_code === $code 
            && $this->two_factor_expiry > new \DateTime();
    }

    public function __toString(): string
    {
        return $this->getFullName() ?: $this->email ?? 'New User';
    }

    // Add these methods to your User class (around line 300-320)

/**
 * Alias for getName() to maintain template compatibility
 */
public function getFirstName(): ?string
{
    return $this->name;
}

/**
 * Alias for getLastName() to maintain template compatibility
 */


/**
 * Return null for fields that don't exist (to prevent template errors)
 */
public function getPhone(): ?string
{
    return null;
}

/**
 * Return null for fields that don't exist (to prevent template errors)
 */
public function getAddress(): ?string
{
    return null;
}

/**
 * Return date as createdAt (to maintain template compatibility)
 */
public function getCreatedAt(): ?\DateTimeInterface
{
    return $this->date;
}

}