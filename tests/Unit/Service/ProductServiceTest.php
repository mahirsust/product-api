<?php

namespace App\Tests\Unit\Service;

use App\Dto\ProductDto;
use App\Entity\Product;
use App\Exception\ProductNotFoundException;
use App\Exception\ValidationException;
use App\Repository\ProductRepository;
use App\Service\ProductService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProductServiceTest extends TestCase
{
    // Mock objects for dependencies
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|ProductRepository $productRepository;
    private MockObject|ValidatorInterface $validator;
    private MockObject|LoggerInterface $logger;

    // The class under test
    private ProductService $productService;

    /**
     * Set up runs BEFORE EACH test method.
     * 
     * Why setUp()?
     * - Creates fresh mocks for each test
     * - Ensures tests don't affect each other
     * - Reduces code duplication
     */
    protected function setUp(): void
    {
        // Create mock objects
        // Mocks simulate real objects but allow us to control their behavior
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Create service with mocked dependencies
        $this->productService = new ProductService(
            $this->entityManager,
            $this->productRepository,
            $this->validator,
            $this->logger
        );
    }

    // =====================================================================
    // getAllProducts() TESTS
    // =====================================================================

    /**
     * Test: Returns empty result when no products exist.
     * 
     * Why test this?
     * - Ensures correct structure even with no data
     * - Verifies meta information is accurate
     * - Edge case: empty database
     */
    public function testGetAllProductsReturnsEmptyArrayWhenNoProducts(): void
    {
        // ARRANGE: Set up mock behavior
        $this->productRepository
            ->expects($this->once())           // Verify called exactly once
            ->method('findBy')                  // The method to mock
            ->with([], ['createdAt' => 'DESC'], 10, 0)  // Expected arguments
            ->willReturn([]);                   // What it returns

        $this->productRepository
            ->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(0);

        // ACT: Execute the method
        $result = $this->productService->getAllProducts();

        // ASSERT: Verify results
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEmpty($result['data']);
        $this->assertEquals(0, $result['meta']['total']);
        $this->assertEquals(1, $result['meta']['page']);
        $this->assertEquals(10, $result['meta']['limit']);
        $this->assertEquals(0, $result['meta']['pages']);
    }

    /**
     * Test: Returns products with correct pagination metadata.
     */
    public function testGetAllProductsReturnsPaginatedResults(): void
    {
        // ARRANGE
        $products = [
            $this->createProductEntity(1, 'Product 1'),
            $this->createProductEntity(2, 'Product 2'),
        ];

        $this->productRepository
            ->method('findBy')
            ->willReturn($products);

        $this->productRepository
            ->method('count')
            ->willReturn(25);  // Total 25 products, showing 2

        // ACT
        $result = $this->productService->getAllProducts(1, 10);

        // ASSERT
        $this->assertCount(2, $result['data']);
        $this->assertEquals(25, $result['meta']['total']);
        $this->assertEquals(1, $result['meta']['page']);
        $this->assertEquals(10, $result['meta']['limit']);
        $this->assertEquals(3, $result['meta']['pages']);  // ceil(25/10) = 3
    }

    /**
     * Test: Calculates correct offset for pagination.
     * 
     * Page 3 with limit 10 should skip first 20 items.
     */
    public function testGetAllProductsCalculatesCorrectOffset(): void
    {
        // ARRANGE: Verify findBy is called with correct offset
        $this->productRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(
                [],                      // criteria
                ['createdAt' => 'DESC'], // orderBy
                10,                      // limit
                20                       // offset = (3-1) * 10 = 20
            )
            ->willReturn([]);

        $this->productRepository->method('count')->willReturn(0);

        // ACT
        $this->productService->getAllProducts(page: 3, limit: 10);
    }

    /**
     * Test: Enforces maximum limit of 100.
     * 
     * Why?
     * - Prevents memory issues with huge result sets
     * - Protects against denial of service
     */
    public function testGetAllProductsEnforcesMaxLimit(): void
    {
        $this->productRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 100, 0)  // Max is 100, not 500
            ->willReturn([]);

        $this->productRepository->method('count')->willReturn(0);

        $this->productService->getAllProducts(page: 1, limit: 500);
    }

    /**
     * Test: Converts negative page to page 1.
     */
    public function testGetAllProductsEnforcesMinimumPage(): void
    {
        $this->productRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 10, 0)  // Offset 0 = page 1
            ->willReturn([]);

        $this->productRepository->method('count')->willReturn(0);

        $this->productService->getAllProducts(page: -5, limit: 10);
    }

    /**
     * Test: Converts zero limit to 1.
     */
    public function testGetAllProductsEnforcesMinimumLimit(): void
    {
        $this->productRepository
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 1, 0)  // Min limit is 1
            ->willReturn([]);

        $this->productRepository->method('count')->willReturn(0);

        $this->productService->getAllProducts(page: 1, limit: 0);
    }

    // =====================================================================
    // getProductById() TESTS
    // =====================================================================

    /**
     * Test: Returns product when found.
     */
    public function testGetProductByIdReturnsProduct(): void
    {
        // ARRANGE
        $product = $this->createProductEntity(1, 'Test Product');

        $this->productRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($product);

        // ACT
        $result = $this->productService->getProductById(1);

        // ASSERT
        $this->assertSame($product, $result);
        $this->assertEquals('Test Product', $result->getName());
    }

    /**
     * Test: Throws ProductNotFoundException when not found.
     * 
     * Why test exceptions?
     * - Ensures correct exception type is thrown
     * - Verifies exception message is helpful
     * - Documents expected error behavior
     */
    public function testGetProductByIdThrowsNotFoundExceptionWhenProductDoesNotExist(): void
    {
        // ARRANGE
        $this->productRepository
            ->method('find')
            ->with(999)
            ->willReturn(null);

        // ASSERT: Exception expected
        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage('Product with ID 999 was not found.');

        // ACT
        $this->productService->getProductById(999);
    }

    /**
     * Test: Logs warning when product not found.
     */
    public function testGetProductByIdLogsWarningWhenNotFound(): void
    {
        $this->productRepository->method('find')->willReturn(null);

        // ASSERT: Logger should be called
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Product not found', ['id' => 123]);

        $this->expectException(ProductNotFoundException::class);

        $this->productService->getProductById(123);
    }

    // =====================================================================
    // createProduct() TESTS
    // =====================================================================

    /**
     * Test: Successfully creates a product.
     */
    public function testCreateProductSuccessfully(): void
    {
        // ARRANGE
        $dto = new ProductDto();
        $dto->name = 'New Product';
        $dto->description = 'A great product';
        $dto->price = 29.99;
        $dto->quantity = 10;

        // Validator returns no errors
        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn(new ConstraintViolationList());

        // EntityManager should persist and flush
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($product) {
                // Verify the product has correct data
                return $product instanceof Product
                    && $product->getName() === 'New Product'
                    && $product->getPrice() === '29.99';
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // ACT
        $result = $this->productService->createProduct($dto);

        // ASSERT
        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('New Product', $result->getName());
        $this->assertEquals('A great product', $result->getDescription());
        $this->assertEquals('29.99', $result->getPrice());
        $this->assertEquals(10, $result->getQuantity());
    }

    /**
     * Test: Throws ValidationException when DTO is invalid.
     */
    public function testCreateProductThrowsValidationExceptionOnInvalidData(): void
    {
        // ARRANGE
        $dto = new ProductDto();
        $dto->name = '';  // Invalid: empty name

        // Create validation violation
        $violation = new ConstraintViolation(
            message: 'Product name is required',
            messageTemplate: null,
            parameters: [],
            root: $dto,
            propertyPath: 'name',
            invalidValue: ''
        );

        $this->validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList([$violation]));

        // EntityManager should NOT be called
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        // ASSERT & ACT
        $this->expectException(ValidationException::class);
        $this->productService->createProduct($dto);
    }

    /**
     * Test: ValidationException contains formatted errors.
     */
    public function testCreateProductValidationExceptionContainsFormattedErrors(): void
    {
        $dto = new ProductDto();

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Name is required', null, [], $dto, 'name', null),
            new ConstraintViolation('Price must be positive', null, [], $dto, 'price', -10),
        ]);

        $this->validator->method('validate')->willReturn($violations);

        try {
            $this->productService->createProduct($dto);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('price', $errors);
            $this->assertEquals('Name is required', $errors['name']);
            $this->assertEquals('Price must be positive', $errors['price']);
        }
    }

    /**
     * Test: Logs product creation.
     */
    public function testCreateProductLogsCreation(): void
    {
        $dto = new ProductDto();
        $dto->name = 'Logged Product';
        $dto->price = 10.00;
        $dto->quantity = 5;

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Product created', $this->callback(function ($context) {
                return isset($context['name']) && $context['name'] === 'Logged Product';
            }));

        $this->productService->createProduct($dto);
    }

    // =====================================================================
    // updateProduct() TESTS
    // =====================================================================

    /**
     * Test: Full update replaces all fields.
     */
    public function testUpdateProductFullUpdate(): void
    {
        // ARRANGE: Existing product
        $product = $this->createProductEntity(1, 'Old Name');
        $product->setDescription('Old Description');
        $product->setPrice('10.00');
        $product->setQuantity(5);

        // DTO with new values
        $dto = new ProductDto();
        $dto->name = 'New Name';
        $dto->description = 'New Description';
        $dto->price = 99.99;
        $dto->quantity = 50;

        $this->productRepository->method('find')->with(1)->willReturn($product);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->entityManager->expects($this->once())->method('flush');

        // ACT
        $result = $this->productService->updateProduct(1, $dto, partial: false);

        // ASSERT: All fields updated
        $this->assertEquals('New Name', $result->getName());
        $this->assertEquals('New Description', $result->getDescription());
        $this->assertEquals('99.99', $result->getPrice());
        $this->assertEquals(50, $result->getQuantity());
    }

    /**
     * Test: Partial update only changes provided fields.
     */
    public function testUpdateProductPartialUpdateOnlyChangesProvidedFields(): void
    {
        // ARRANGE: Existing product
        $product = $this->createProductEntity(1, 'Original Name');
        $product->setDescription('Original Description');
        $product->setPrice('50.00');
        $product->setQuantity(100);

        // DTO with only price (other fields null)
        $dto = new ProductDto();
        $dto->price = 75.00;

        $this->productRepository->method('find')->with(1)->willReturn($product);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        // ACT
        $result = $this->productService->updateProduct(1, $dto, partial: true);

        // ASSERT: Only price changed
        $this->assertEquals('Original Name', $result->getName());
        $this->assertEquals('Original Description', $result->getDescription());
        $this->assertEquals('75', $result->getPrice());  // Changed
        $this->assertEquals(100, $result->getQuantity());
    }

    /**
     * Test: Update throws NotFoundException for invalid ID.
     */
    public function testUpdateProductThrowsNotFoundForInvalidId(): void
    {
        $this->productRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(ProductNotFoundException::class);

        $dto = new ProductDto();
        $dto->name = 'Test';
        $this->productService->updateProduct(999, $dto);
    }

    /**
     * Test: Update throws ValidationException for invalid data.
     */
    public function testUpdateProductThrowsValidationExceptionForInvalidData(): void
    {
        $product = $this->createProductEntity(1, 'Test');
        $this->productRepository->method('find')->willReturn($product);

        $dto = new ProductDto();
        $dto->price = -50;  // Invalid

        $violation = new ConstraintViolation(
            'Price must be positive',
            null,
            [],
            $dto,
            'price',
            -50
        );
        $this->validator->method('validate')->willReturn(new ConstraintViolationList([$violation]));

        // EntityManager should NOT flush
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(ValidationException::class);
        $this->productService->updateProduct(1, $dto);
    }

    // =====================================================================
    // deleteProduct() TESTS
    // =====================================================================

    /**
     * Test: Successfully deletes a product.
     */
    public function testDeleteProductSuccessfully(): void
    {
        $product = $this->createProductEntity(1, 'To Delete');

        $this->productRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($product);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // ACT: Should not throw
        $this->productService->deleteProduct(1);
    }

    /**
     * Test: Delete throws NotFoundException for invalid ID.
     */
    public function testDeleteProductThrowsNotFoundForInvalidId(): void
    {
        $this->productRepository->method('find')->with(999)->willReturn(null);

        // Should NOT call remove or flush
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(ProductNotFoundException::class);
        $this->productService->deleteProduct(999);
    }

    /**
     * Test: Logs deletion.
     */
    public function testDeleteProductLogsOperation(): void
    {
        $product = $this->createProductEntity(5, 'Logged Delete');
        $this->productRepository->method('find')->willReturn($product);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Product deleted', ['id' => 5]);

        $this->productService->deleteProduct(5);
    }

    // =====================================================================
    // HELPER METHODS
    // =====================================================================

    /**
     * Create a Product entity with ID set via reflection.
     * 
     * Why reflection?
     * - Doctrine normally sets the ID
     * - In unit tests, we need to control the ID
     * - Reflection allows setting private properties
     */
    private function createProductEntity(int $id, string $name): Product
    {
        $product = new Product();

        // Use reflection to set the private ID property
        (new \ReflectionProperty(Product::class, 'id'))
            ->setValue($product, $id);

        $product->setName($name);
        $product->setDescription('Test description');
        $product->setPrice('99.99');
        $product->setQuantity(10);

        return $product;
    }
}