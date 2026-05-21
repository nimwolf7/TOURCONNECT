<?php

namespace App\Entity;

use App\Repository\BudgetTrackerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BudgetTrackerRepository::class)]
class BudgetTracker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Booking $booking = null;

    #[ORM\Column(length: 255)]
    private ?string $category = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amountPlanned = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $amountSpent = null;

    #[ORM\Column(length: 70)]
    private ?string $dateRange = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getAmountPlanned(): ?string
    {
        return $this->amountPlanned;
    }

    public function setAmountPlanned(string $amountPlanned): static
    {
        $this->amountPlanned = $amountPlanned;

        return $this;
    }

    public function getAmountSpent(): ?string
    {
        return $this->amountSpent;
    }

    public function setAmountSpent(string $amountSpent): static
    {
        $this->amountSpent = $amountSpent;

        return $this;
    }

    public function getDateRange(): ?string
    {
        return $this->dateRange;
    }

    public function setDateRange(string $dateRange): static
    {
        $this->dateRange = $dateRange;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }
}
