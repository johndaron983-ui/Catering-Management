<?php

namespace App\Entity;

use App\Repository\ServicesRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Booking;
use App\Entity\Inventory;
use App\Entity\User;

#[ORM\Entity(repositoryClass: ServicesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Services
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $eventType = null;

    #[ORM\Column]
    private ?float $basePrice = null;

    #[ORM\Column]
    private ?int $minGuests = null;

    #[ORM\Column]
    private ?int $maxGuests = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: Booking::class, orphanRemoval: true)]
    private Collection $bookings;

    /**
     * @var Collection<int, Inventory>
     */
    #[ORM\ManyToMany(targetEntity: Inventory::class, inversedBy: 'services')]
    private Collection $inventory;

    /**
     * @var Collection<int, ServiceInventory>
     */
    #[ORM\OneToMany(mappedBy: 'service', targetEntity: ServiceInventory::class, orphanRemoval: true)]
    private Collection $serviceInventories;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getBasePrice(): ?float
    {
        return $this->basePrice;
    }

    public function setBasePrice(float $basePrice): static
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getMinGuests(): ?int
    {
        return $this->minGuests;
    }

    public function setMinGuests(int $minGuests): static
    {
        $this->minGuests = $minGuests;

        return $this;
    }

    public function getMaxGuests(): ?int
    {
        return $this->maxGuests;
    }

    public function setMaxGuests(int $maxGuests): static
    {
        $this->maxGuests = $maxGuests;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
        $this->inventory = new ArrayCollection();
        $this->serviceInventories = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setService($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getService() === $this) {
                $booking->setService(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Inventory>
     */
    public function getInventory(): Collection
    {
        return $this->inventory;
    }

    public function addInventory(Inventory $inventory): static
    {
        if (!$this->inventory->contains($inventory)) {
            $this->inventory->add($inventory);
        }
        return $this;
    }

    public function removeInventory(Inventory $inventory): static
    {
        $this->inventory->removeElement($inventory);
        return $this;
    }

    /**
     * @return Collection<int, ServiceInventory>
     */
    public function getServiceInventories(): Collection
    {
        return $this->serviceInventories;
    }

    public function addServiceInventory(ServiceInventory $serviceInventory): static
    {
        if (!$this->serviceInventories->contains($serviceInventory)) {
            $this->serviceInventories->add($serviceInventory);
            $serviceInventory->setService($this);
        }
        return $this;
    }

    public function removeServiceInventory(ServiceInventory $serviceInventory): static
    {
        if ($this->serviceInventories->removeElement($serviceInventory)) {
            if ($serviceInventory->getService() === $this) {
                $serviceInventory->setService(null);
            }
        }
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $user): static
    {
        $this->createdBy = $user;
        return $this;
    }
}
