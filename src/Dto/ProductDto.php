<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

/**
 * Data Transfer Object for product input.
 * 
 * This DTO:
 * - Defines the shape of incoming product data
 * - Contains validation rules
 * - Provides OpenAPI documentation
 * 
 * Validation groups:
 * - Default: Used for create and full update (PUT)
 * - partial: Used for partial update (PATCH)
 */

class ProductDto
{
    #[Assert\NotBlank(message: "Product name is required", groups: ['Default'])]
    #[Assert\Length(
        max: 255,
        maxMessage: "Name cannot exceed 255 characters",
        groups: ['Default', 'partial']
    )]
    #[OA\Property(
        description: "Product name",
        example: "Wireless Keyboard",
        maxLength: 255
    )]
    public ?string $name = null;

    #[Assert\Length(
        max: 5000,
        maxMessage: "Description cannot exceed 5000 characters",
        groups: ['Default', 'partial']
    )]
    #[OA\Property(
        description: "Product description",
        example: "A high-quality wireless keyboard with RGB lighting",
        maxLength: 5000
    )]
    public ?string $description = null;

    #[Assert\NotBlank(message: "Price is required", groups: ['Default'])]
    #[Assert\Positive(
        message: "Price must be a positive number",
        groups: ['Default', 'partial']
    )]
    #[Assert\LessThan(
        value: 1000000,
        message: "Price cannot exceed 999,999.99",
        groups: ['Default', 'partial']
    )]
    #[OA\Property(
        description: "Product price in dollars",
        example: 49.99,
        minimum: 0.01,
        maximum: 999999.99
    )]
    public ?float $price = null;

    #[Assert\NotBlank(message: "Quantity is required", groups: ['Default'])]
    #[Assert\PositiveOrZero(
        message: "Quantity cannot be negative",
        groups: ['Default', 'partial']
    )]
    #[Assert\LessThan(
        value: 1000000,
        message: "Quantity cannot exceed 999,999",
        groups: ['Default', 'partial']
    )]
    #[OA\Property(
        description: "Stock quantity available",
        example: 100,
        minimum: 0,
        maximum: 999999
    )]
    public ?int $quantity = null;

    /**
     * Factory method to create DTO from array.
     * Useful for testing and manual creation.
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->name = $data['name'] ?? null;
        $dto->description = $data['description'] ?? null;
        $dto->price = isset($data['price']) ? (float) $data['price'] : null;
        $dto->quantity = isset($data['quantity']) ? (int) $data['quantity'] : null;
        
        return $dto;
    }
}