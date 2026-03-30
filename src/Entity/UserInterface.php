<?php
// src/Entity/UserInterface.php

namespace App\Entity;

use Doctrine\Common\Collections\Collection;

interface UserInterface
{
    // ... all the same methods as before ...
    public function getId(): ?int;
    public function getEmail(): ?string;
    public function setEmail(string $email): static;
    public function getFirstName(): ?string;
    public function setFirstName(string $firstName): static;
    public function getLastName(): ?string;
    public function setLastName(string $lastName): static;
    public function getFullName(): string;
    public function getPhone(): ?string;
    public function setPhone(?string $phone): static;
    public function getAddress(): ?string;
    public function setAddress(?string $address): static;
    public function getCreatedAt(): ?\DateTimeImmutable;
    public function getRoles(): array;
    public function setRoles(array $roles): static;
    public function hasRole(string $role): bool;
    public function addRole(string $role): static;
    public function removeRole(string $role): static;
    public function getPassword(): string;
    public function setPassword(string $password): static;
    public function hasPassword(): bool;
    public function getShops(): Collection;
    public function addShop(Shop $shop): static;
    public function removeShop(Shop $shop): static;
    public function getShopsCount(): int;
    public function getTodos(): Collection;
    public function addTodo(Todo $todo): static;
    public function removeTodo(Todo $todo): static;
    public function getTodosCount(): int;
    public function getCompletedTodosCount(): int;
    public function getPendingTodosCount(): int;
    public function getSentMessages(): Collection;
    public function getReceivedMessages(): Collection;
    public function getAllMessages(): Collection;
    public function getUnreadMessagesCount(): int;
    public function getPurchases(): Collection;
    public function addPurchase(Purchase $purchase): static;
    public function removePurchase(Purchase $purchase): static;
    public function getTotalSpent(): float;
    public function getPurchasesCount(): int;
    public function getLastPurchaseDate(): ?\DateTimeImmutable;
    public function hasShops(): bool;
    public function hasTodos(): bool;
    public function hasMessages(): bool;
    public function hasPurchases(): bool;
    public function getInitials(): string;
    public function isProfileComplete(): bool;
    public function getProfileCompletionPercentage(): int;
    public function getAccountAgeInDays(): int;
    public function __toString(): string;
}