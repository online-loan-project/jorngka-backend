<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait BaseMobileApiResponse
{
    /**
     * Standard success response for mobile APIs
     */
    public function mobileSuccess(
        $data = null,
        ?string $message = null,
        ?array $metadata = null,
        int $code = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message ?? 'Operation successful',
        ];

        if ($metadata) {
            $response['metadata'] = $metadata;
        }

        return response()->json($response, $code);
    }

    /**
     * Success response with authentication token (for login/refresh)
     */
    public function mobileAuthSuccess(
        $user,
        string $token,
        ?string $message = null,
        ?array $additionalData = null
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'bearer',
            ],
            'message' => $message ?? 'Authentication successful',
        ];

        if ($additionalData) {
            $response['data'] = array_merge($response['data'], $additionalData);
        }

        return response()->json($response);
    }

    /**
     * Paginated data response with mobile-friendly structure
     */
    public function mobilePaginated(
        LengthAwarePaginator $paginator,
        ?string $message = null,
        ?array $additionalData = null
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'message' => $message ?? 'Data retrieved successfully',
        ];

        if ($additionalData) {
            $response = array_merge($response, $additionalData);
        }

        return response()->json($response);
    }

    /**
     * Error response for mobile clients
     */
    public function mobileError(
        ?string $message = null,
        ?array $errors = null,
        int $code = 400,
        ?array $additionalData = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message ?? 'An error occurred',
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        if ($additionalData) {
            $response = array_merge($response, $additionalData);
        }

        return response()->json($response, $code);
    }

    /**
     * Resource not found response
     */
    public function mobileNotFound(?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message ?? 'Resource not found',
        ], 404);
    }

    /**
     * Forbidden/unauthorized access response
     */
    public function mobileForbidden(?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message ?? 'Unauthorized access',
        ], 403);
    }
}