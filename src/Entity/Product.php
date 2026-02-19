<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use OpenApi\Attributes as OA;

/**
 * Product entity representing items in the catalog.
 * 
 * This entity:
 * - Maps to the 'product' database table
 * - Uses lifecycle callbacks for automatic timestamps
 * - Defines serialization groups for API responses
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'product')]
#[ORM\Index(columns: ['name'], name: 'idx_product_name')]
#[ORM\Index(columns: ['price'], name: 'idx_product_price')]
#[ORM\Index(columns: ['created_at'], name: 'idx_product_created_at')]
#[OA\Schema(description: "Product entity representing items in the catalog")]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read'])]
    #[OA\Property(description: "Unique identifier", example: 1, readOnly: true)]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product:read', 'product:write'])]
    #[OA\Property(description: "Product name", example: "Wireless Keyboard")]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    #[OA\Property(description: "Detailed product description", example: "High-quality wireless keyboard with RGB lighting")]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['product:read', 'product:write'])]
    #[OA\Property(description: "Price in dollars", example: "49.99")]
    private ?string $price = null;

    #[ORM\Column]
    #[Groups(['product:read', 'product:write'])]
    #[OA\Property(description: "Available quantity in stock", example: 100)]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Groups(['product:read'])]
    #[OA\Property(description: "Timestamp when the product was created", readOnly: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:read'])]
    #[OA\Property(description: "Timestamp when the product was last updated", readOnly: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if product is in stock.
     */
    #[Groups(['product:read'])]
    #[OA\Property(description: "Whether the product is currently in stock", example: true)]
    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }
}