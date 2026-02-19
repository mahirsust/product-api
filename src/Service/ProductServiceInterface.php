<?php

namespace App\Service;

use App\Dto\ProductDto;
use App\Entity\Product;

/**
 * Interface for product business operations.
 * 
 * Using an interface allows for:
 * - Easy mocking in tests
 * - Multiple implementations (e.g., cached version)
 * - Clear contract definition
 */
interface ProductServiceInterface
{
    /**
     * Retrieve paginated list of products.
     *
     * @return array{data: Product[], meta: array{total: int, page: int, limit: int, pages: int}}
     */
    public function getAllProducts(int $page = 1, int $limit = 10): array;

    /**
     * Find a single product by ID.
     *
     * @throws ProductNotFoundException
     */
    public function getProductById(int $id): Product;

    /**
     * Create a new product from DTO.
     *
     * @throws ValidationException
     */
    public function createProduct(ProductDto $dto): Product;

    /**
     * Update an existing product.
     *
     * @param bool $partial If true, only update provided fields (PATCH behavior)
     * @throws ProductNotFoundException
     * @throws ValidationException
     */
    public function updateProduct(int $id, ProductDto $dto, bool $partial = false): Product;

    /**
     * Delete a product by ID.
     *
     * @throws ProductNotFoundException
     */
    public function deleteProduct(int $id): void;

    /**
     * Search products by criteria.
     *
     * @return array{data: Product[], meta: array{total: int, page: int, limit: int, pages: int}}
     */
    public function searchProducts(array $criteria, int $page = 1, int $limit = 10): array;
}