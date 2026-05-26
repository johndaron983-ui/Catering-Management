<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Services $service = null;

    #[ORM\Column(length: 255)]
    private ?string $customerName = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Please select a booking date.')]
    #[Assert\GreaterThanOrEqual(
        value: 'today',
        message: 'The booking date must be today or a future date. Past dates are not allowed.'
    )]
    private ?\DateTimeImmutable $eventDate = null;

    #[ORM\Column(length: 32)]
    private ?string $status = 'pending';

    #[ORM\Column]
    private ?int $guestCount = 0;

    #[ORM\Column]
    private ?float $totalPrice = 0000;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /**
     * @var Collection<int, BookingInventory>
     */
    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: BookingInventory::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $bookingInventories;

    public function __construct()
    {
        $this->bookingInventories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getService(): ?Services
    {
        return $this->service;
    }

    public function setService(?Services $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function getEventDate(): ?\DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeImmutable $eventDate): self
    {
        $this->eventDate = $eventDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getGuestCount(): ?int
    {
        return $this->guestCount;
    }

    public function setGuestCount(int $guestCount): self
    {
        $this->guestCount = $guestCount;
        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $user): self
    {
        $this->createdBy = $user;
        return $this;
    }

    /**
     * @return Collection<int, BookingInventory>
     */
    public function getBookingInventories(): Collection
    {
        return $this->bookingInventories;
    }

    public function addBookingInventory(BookingInventory $bookingInventory): self
    {
        if (!$this->bookingInventories->contains($bookingInventory)) {
            $this->bookingInventories->add($bookingInventory);
            $bookingInventory->setBooking($this);
        }
        return $this;
    }

    public function removeBookingInventory(BookingInventory $bookingInventory): self
    {
        if ($this->bookingInventories->removeElement($bookingInventory)) {
            if ($bookingInventory->getBooking() === $this) {
                $bookingInventory->setBooking(null);
            }
        }
        return $this;
    }
}


