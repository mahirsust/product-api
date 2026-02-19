<?php

namespace App\Controller\Api;

use App\Dto\ProductDto;
use App\Entity\Product;
use App\Exception\ProductNotFoundException;
use App\Exception\ValidationException;
use App\Service\ProductServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

/**
 * REST API controller for product operations.
 * 
 * This controller:
 * - Handles HTTP request/response
 * - Delegates business logic to ProductService
 * - Handles exceptions and returns appropriate responses
 * - Provides OpenAPI documentation
 */
#[Route('/api/products')]
#[OA\Tag(name: "Products", description: "Product management endpoints")]
class ProductController extends AbstractController
{
    public function __construct(
        private ProductServiceInterface $productService,
        private SerializerInterface $serializer
    ) {}

    /**
     * List all products with pagination.
     */
    #[Route('', name: 'api_products_index', methods: ['GET'])]
    #[OA\Get(
        summary: "List all products",
        description: "Retrieves a paginated list of all products, ordered by creation date (newest first)"
    )]
    #[OA\Parameter(
        name: "page",
        in: "query",
        description: "Page number (starts at 1)",
        required: false,
        schema: new OA\Schema(type: "integer", default: 1, minimum: 1)
    )]
    #[OA\Parameter(
        name: "limit",
        in: "query",
        description: "Number of items per page (max 100)",
        required: false,
        schema: new OA\Schema(type: "integer", default: 10, minimum: 1, maximum: 100)
    )]
    #[OA\Response(
        response: 200,
        description: "Products retrieved successfully",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(ref: new Model(type: Product::class, groups: ["product:read"]))
                ),
                new OA\Property(
                    property: "meta",
                    type: "object",
                    properties: [
                        new OA\Property(property: "total", type: "integer", example: 50),
                        new OA\Property(property: "page", type: "integer", example: 1),
                        new OA\Property(property: "limit", type: "integer", example: 10),
                        new OA\Property(property: "pages", type: "integer", example: 5)
                    ]
                )
            ]
        )
    )]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $result = $this->productService->getAllProducts($page, $limit);

        return $this->json($result, Response::HTTP_OK, [], ['groups' => 'product:read']);
    }

    /**
     * Search products with filters.
     */
    #[Route('/search', name: 'api_products_search', methods: ['GET'])]
    #[OA\Get(
        summary: "Search products",
        description: "Search products with various filters"
    )]
    #[OA\Parameter(
        name: "name",
        in: "query",
        description: "Search by product name (partial match)",
        required: false,
        schema: new OA\Schema(type: "string")
    )]
    #[OA\Parameter(
        name: "minPrice",
        in: "query",
        description: "Minimum price filter",
        required: false,
        schema: new OA\Schema(type: "number")
    )]
    #[OA\Parameter(
        name: "maxPrice",
        in: "query",
        description: "Maximum price filter",
        required: false,
        schema: new OA\Schema(type: "number")
    )]
    #[OA\Parameter(
        name: "inStock",
        in: "query",
        description: "Filter to only in-stock products",
        required: false,
        schema: new OA\Schema(type: "boolean")
    )]
    #[OA\Parameter(
        name: "page",
        in: "query",
        description: "Page number",
        required: false,
        schema: new OA\Schema(type: "integer", default: 1)
    )]
    #[OA\Parameter(
        name: "limit",
        in: "query",
        description: "Items per page",
        required: false,
        schema: new OA\Schema(type: "integer", default: 10)
    )]
    #[OA\Response(
        response: 200,
        description: "Search results",
        content: new OA\JsonContent(
            type: "object",
            properties: [
                new OA\Property(
                    property: "data",
                    type: "array",
                    items: new OA\Items(ref: new Model(type: Product::class, groups: ["product:read"]))
                ),
                new OA\Property(property: "meta", type: "object")
            ]
        )
    )]
    public function search(Request $request): JsonResponse
    {
        $criteria = [];

        if ($request->query->has('name')) {
            $criteria['name'] = $request->query->get('name');
        }
        if ($request->query->has('minPrice')) {
            $criteria['minPrice'] = $request->query->get('minPrice');
        }
        if ($request->query->has('maxPrice')) {
            $criteria['maxPrice'] = $request->query->get('maxPrice');
        }
        if ($request->query->has('inStock')) {
            $criteria['inStock'] = $request->query->getBoolean('inStock');
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $result = $this->productService->searchProducts($criteria, $page, $limit);

        return $this->json($result, Response::HTTP_OK, [], ['groups' => 'product:read']);
    }

    /**
     * Get a single product by ID.
     */
    #[Route('/{id}', name: 'api_products_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        summary: "Get a product",
        description: "Retrieves detailed information about a specific product"
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        description: "Product ID",
        required: true,
        schema: new OA\Schema(type: "integer", minimum: 1)
    )]
    #[OA\Response(
        response: 200,
        description: "Product found",
        content: new OA\JsonContent(ref: new Model(type: Product::class, groups: ["product:read"]))
    )]
    #[OA\Response(
        response: 404,
        description: "Product not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "error", type: "string", example: "Product with ID 123 was not found.")
            ]
        )
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $product = $this->productService->getProductById($id);
            return $this->json($product, Response::HTTP_OK, [], ['groups' => 'product:read']);
        } catch (ProductNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Create a new product.
     */
    #[Route('', name: 'api_products_create', methods: ['POST'])]
    #[OA\Post(
        summary: "Create a product",
        description: "Creates a new product with the provided data"
    )]
    #[OA\RequestBody(
        required: true,
        description: "Product data",
        content: new OA\JsonContent(
            ref: new Model(type: ProductDto::class),
            example: [
                "name" => "Wireless Keyboard",
                "description" => "High-quality wireless keyboard with RGB lighting",
                "price" => 49.99,
                "quantity" => 100
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Product created successfully",
        content: new OA\JsonContent(ref: new Model(type: Product::class, groups: ["product:read"]))
    )]
    #[OA\Response(
        response: 400,
        description: "Validation error",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: "errors",
                    type: "object",
                    example: ["name" => "Product name is required", "price" => "Price must be positive"]
                )
            ]
        )
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                ProductDto::class,
                'json'
            );

            $product = $this->productService->createProduct($dto);

            return $this->json(
                $product,
                Response::HTTP_CREATED,
                ['Location' => $this->generateUrl('api_products_show', ['id' => $product->getId()])],
                ['groups' => 'product:read']
            );
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Fully update an existing product (PUT).
     */
    #[Route('/{id}', name: 'api_products_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        summary: "Update a product (full)",
        description: "Replaces all product data with the provided values. All fields are required."
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        description: "Product ID",
        required: true,
        schema: new OA\Schema(type: "integer")
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: new Model(type: ProductDto::class))
    )]
    #[OA\Response(
        response: 200,
        description: "Product updated successfully",
        content: new OA\JsonContent(ref: new Model(type: Product::class, groups: ["product:read"]))
    )]
    #[OA\Response(response: 400, description: "Validation error")]
    #[OA\Response(response: 404, description: "Product not found")]
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                ProductDto::class,
                'json'
            );

            $product = $this->productService->updateProduct($id, $dto, partial: false);

            return $this->json($product, Response::HTTP_OK, [], ['groups' => 'product:read']);
        } catch (ProductNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Partially update an existing product (PATCH).
     */
    #[Route('/{id}', name: 'api_products_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(
        summary: "Update a product (partial)",
        description: "Updates only the provided fields. Omitted fields remain unchanged."
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        description: "Product ID",
        required: true,
        schema: new OA\Schema(type: "integer")
    )]
    #[OA\RequestBody(
        required: true,
        description: "Fields to update",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "name", type: "string", example: "Updated Name"),
                new OA\Property(property: "price", type: "number", example: 59.99)
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Product updated successfully",
        content: new OA\JsonContent(ref: new Model(type: Product::class, groups: ["product:read"]))
    )]
    #[OA\Response(response: 400, description: "Validation error")]
    #[OA\Response(response: 404, description: "Product not found")]
    public function patch(Request $request, int $id): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                ProductDto::class,
                'json'
            );

            $product = $this->productService->updateProduct($id, $dto, partial: true);

            return $this->json($product, Response::HTTP_OK, [], ['groups' => 'product:read']);
        } catch (ProductNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (ValidationException $e) {
            return $this->json(['errors' => $e->getErrors()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a product.
     */
    #[Route('/{id}', name: 'api_products_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(
        summary: "Delete a product",
        description: "Permanently removes a product from the system"
    )]
    #[OA\Parameter(
        name: "id",
        in: "path",
        description: "Product ID",
        required: true,
        schema: new OA\Schema(type: "integer")
    )]
    #[OA\Response(
        response: 204,
        description: "Product deleted successfully"
    )]
    #[OA\Response(response: 404, description: "Product not found")]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->productService->deleteProduct($id);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (ProductNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}