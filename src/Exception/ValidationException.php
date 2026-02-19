<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Thrown when input validation fails.
 * 
 * Converts Symfony's ConstraintViolationList into a structured
 * array of errors that can be returned in the API response.
 */
class ValidationException extends BadRequestHttpException
{
    private array $errors;

    public function __construct(ConstraintViolationListInterface $violations)
    {
        $this->errors = $this->formatViolations($violations);
        
        parent::__construct('Validation failed.');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function formatViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        
        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();
            $errors[$property] = $violation->getMessage();
        }
        
        return $errors;
    }
}