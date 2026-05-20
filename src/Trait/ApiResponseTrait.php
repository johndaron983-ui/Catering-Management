<?php

namespace App\Trait;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Trait for standardized API JSON responses.
 * Provides consistent response format for mobile API consumption.
 */
trait ApiResponseTrait
{
    /**
     * Return a successful JSON response.
     *
     * @param mixed $data The data to return
     * @param string $message Success message
     * @param int $statusCode HTTP status code
     * @param array $meta Additional metadata (pagination, counts, etc.)
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return new JsonResponse($response, $statusCode);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param mixed $errors Detailed error data (validation errors, etc.)
     */
    protected function errorResponse(
        string $message = 'Error',
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return new JsonResponse($response, $statusCode);
    }

    /**
     * Return a paginated JSON response.
     *
     * @param mixed $data The paginated data
     * @param int $page Current page number
     * @param int $limit Items per page
     * @param int $total Total number of items
     * @param string $message Success message
     */
    protected function paginatedResponse(
        mixed $data,
        int $page,
        int $limit,
        int $total,
        string $message = 'Success'
    ): JsonResponse {
        $totalPages = (int) ceil($total / $limit);

        $meta = [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $total,
                'total_pages' => $totalPages,
                'has_next_page' => $page < $totalPages,
                'has_previous_page' => $page > 1,
            ],
        ];

        return $this->successResponse($data, $message, 200, $meta);
    }
}
