<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Thrown when a requested product does not exist.
 * 
 * Extends NotFoundHttpException so Symfony automatically
 * converts this to a 404 HTTP response.
 */
class ProductNotFoundException extends NotFoundHttpException
{
    public function __construct(int $productId)
    {
        parent::__construct(
            message: sprintf('Product with ID %d was not found.', $productId)
        );
    }
}