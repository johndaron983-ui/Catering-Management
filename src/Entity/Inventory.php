<?php

namespace App\Entity;

use App\Repository\InventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Services;
use App\Entity\User;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
#[ORM\Table(name: 'inventory')]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Item name must be at least {{ limit }} characters', maxMessage: 'Item name cannot be longer than {{ limit }} characters')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Category is required')]
    private ?string $category = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Current stock is required')]
    #[Assert\PositiveOrZero(message: 'Current stock must be 0 or greater')]
    private ?int $currentStock = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Minimum stock is required')]
    #[Assert\PositiveOrZero(message: 'Minimum stock must be 0 or greater')]
    private ?int $minimumStock = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Maximum stock is required')]
    #[Assert\Positive(message: 'Maximum stock must be greater than 0')]
    private ?int $maximumStock = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Unit price is required')]
    #[Assert\PositiveOrZero(message: 'Unit price must be 0 or greater')]
    private ?string $unitPrice = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Unit is required')]
    private ?string $unit = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "CASCADE")]
    private ?Supplier $supplier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $supplierContact = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastRestocked = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'active';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    

    /**
     * @var Collection<int, Services>
     */
    #[ORM\ManyToMany(targetEntity: Services::class, mappedBy: 'inventory')]
    private Collection $services;

    #[ORM\ManyToOne(inversedBy: 'inventories')]
    #[ORM\JoinColumn(nullable: true, onDelete: "CASCADE")]
    private ?Product $product = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->status = 'active';
        $this->services = new ArrayCollection();
    }

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCurrentStock(): ?int
    {
        return $this->currentStock;
    }

    public function setCurrentStock(int $currentStock): static
    {
        $this->currentStock = $currentStock;
        return $this;
    }

    public function getMinimumStock(): ?int
    {
        return $this->minimumStock;
    }

    public function setMinimumStock(int $minimumStock): static
    {
        $this->minimumStock = $minimumStock;
        return $this;
    }

    public function getMaximumStock(): ?int
    {
        return $this->maximumStock;
    }

    public function setMaximumStock(int $maximumStock): static
    {
        $this->maximumStock = $maximumStock;
        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;
        return $this;
    }

    public function getSupplierContact(): ?string
    {
        return $this->supplierContact;
    }

    public function setSupplierContact(?string $supplierContact): static
    {
        $this->supplierContact = $supplierContact;
        return $this;
    }

    public function getLastRestocked(): ?\DateTimeInterface
    {
        return $this->lastRestocked;
    }

    public function setLastRestocked(?\DateTimeInterface $lastRestocked): static
    {
        $this->lastRestocked = $lastRestocked;
        return $this;
    }

    public function getExpiryDate(): ?\DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeInterface $expiryDate): static
    {
        $this->expiryDate = $expiryDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

   

    

    // Helper methods
    public function isLowStock(): bool
    {
        return $this->currentStock <= $this->minimumStock;
    }

    public function isOutOfStock(): bool
    {
        return $this->currentStock <= 0;
    }

    public function isExpired(): bool
    {
        return $this->expiryDate && $this->expiryDate < new \DateTime();
    }

    public function getStockPercentage(): float
    {
        if ($this->maximumStock <= 0) {
            return 0;
        }
        return ($this->currentStock / $this->maximumStock) * 100;
    }

    public function getTotalValue(): float
    {
        return $this->currentStock * (float) $this->unitPrice;
    }

    public function getStockStatus(): string
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        } elseif ($this->isLowStock()) {
            return 'low_stock';
        } elseif ($this->isExpired()) {
            return 'expired';
        } else {
            return 'in_stock';
        }
    }

    public function getStockStatusColor(): string
    {
        switch ($this->getStockStatus()) {
            case 'out_of_stock':
                return 'red';
            case 'low_stock':
                return 'yellow';
            case 'expired':
                return 'red';
            default:
                return 'green';
        }
    }

    /**
     * @return Collection<int, Services>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Services $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->addInventory($this);
        }

        return $this;
    }

    public function removeService(Services $service): static
    {
        if ($this->services->removeElement($service)) {
            $service->removeInventory($this);
        }

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

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
