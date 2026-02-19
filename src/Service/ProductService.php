<?php

namespace App\Service;

use App\Dto\ProductDto;
use App\Entity\Product;
use App\Exception\ProductNotFoundException;
use App\Exception\ValidationException;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service handling all product-related business logic.
 * 
 * This service:
 * - Validates input data
 * - Performs CRUD operations
 * - Handles business rules
 * - Logs important operations
 */
class ProductService implements ProductServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getAllProducts(int $page = 1, int $limit = 10): array
    {
        // Ensure valid pagination parameters
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $products = $this->productRepository->findBy(
            criteria: [],
            orderBy: ['createdAt' => 'DESC'],
            limit: $limit,
            offset: $offset
        );

        $total = $this->productRepository->count([]);

        $this->logger->debug('Retrieved products list', [
            'page' => $page,
            'limit' => $limit,
            'total' => $total
        ]);

        return [
            'data' => $products,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit)
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getProductById(int $id): Product
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            $this->logger->warning('Product not found', ['id' => $id]);
            throw new ProductNotFoundException($id);
        }

        return $product;
    }

    /**
     * {@inheritdoc}
     */
    public function createProduct(ProductDto $dto): Product
    {
        // Validate the DTO
        $this->validateDto($dto);

        // Create and populate the entity
        $product = new Product();
        $this->mapDtoToEntity($dto, $product);

        // Persist to database
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->logger->info('Product created', [
            'id' => $product->getId(),
            'name' => $product->getName()
        ]);

        return $product;
    }

    /**
     * {@inheritdoc}
     */
    public function updateProduct(int $id, ProductDto $dto, bool $partial = false): Product
    {
        $product = $this->getProductById($id);

        // For full updates (PUT), validate all fields
        // For partial updates (PATCH), only validate provided fields
        if (!$partial) {
            $this->validateDto($dto);
        } else {
            $this->validatePartialDto($dto);
        }

        // Map DTO to entity (handles null values for partial updates)
        $this->mapDtoToEntity($dto, $product, $partial);

        $this->entityManager->flush();

        $this->logger->info('Product updated', [
            'id' => $product->getId(),
            'partial' => $partial
        ]);

        return $product;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteProduct(int $id): void
    {
        $product = $this->getProductById($id);

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        $this->logger->info('Product deleted', ['id' => $id]);
    }

    /**
     * {@inheritdoc}
     */
    public function searchProducts(array $criteria, int $page = 1, int $limit = 10): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->productRepository->createQueryBuilder('p');

        // Apply search filters
        if (isset($criteria['name'])) {
            $queryBuilder
                ->andWhere('p.name LIKE :name')
                ->setParameter('name', '%' . $criteria['name'] . '%');
        }

        if (isset($criteria['minPrice'])) {
            $queryBuilder
                ->andWhere('p.price >= :minPrice')
                ->setParameter('minPrice', $criteria['minPrice']);
        }

        if (isset($criteria['maxPrice'])) {
            $queryBuilder
                ->andWhere('p.price <= :maxPrice')
                ->setParameter('maxPrice', $criteria['maxPrice']);
        }

        if (isset($criteria['inStock']) && $criteria['inStock']) {
            $queryBuilder->andWhere('p.quantity > 0');
        }

        // Get total count before pagination
        $countQuery = clone $queryBuilder;
        $total = $countQuery->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination and ordering
        $products = $queryBuilder
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data' => $products,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit)
            ]
        ];
    }

    /**
     * Validate a DTO for complete data (used in create and full update).
     *
     * @throws ValidationException
     */
    private function validateDto(ProductDto $dto): void
    {
        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }
    }

    /**
     * Validate a DTO for partial data (used in PATCH updates).
     * Only validates fields that are actually provided.
     *
     * @throws ValidationException
     */
    private function validatePartialDto(ProductDto $dto): void
    {
        // For partial updates, we create a temporary DTO with only the provided values
        // and validate just those fields
        $violations = $this->validator->validate($dto, null, ['partial']);

        // Filter violations to only include fields that were actually provided
        $filteredViolations = [];
        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();
            $getter = 'get' . ucfirst($property);
            
            // Only include violation if the field was provided (not null)
            if (property_exists($dto, $property) && $dto->$property !== null) {
                $filteredViolations[] = $violation;
            }
        }

        if (count($filteredViolations) > 0) {
            throw new ValidationException($violations);
        }
    }

    /**
     * Map DTO properties to entity.
     *
     * @param bool $partial If true, only map non-null properties
     */
    private function mapDtoToEntity(ProductDto $dto, Product $product, bool $partial = false): void
    {
        if (!$partial || $dto->name !== null) {
            $product->setName($dto->name);
        }

        if (!$partial || $dto->description !== null) {
            $product->setDescription($dto->description);
        }

        if (!$partial || $dto->price !== null) {
            $product->setPrice((string) $dto->price);
        }

        if (!$partial || $dto->quantity !== null) {
            $product->setQuantity($dto->quantity);
        }
    }
}