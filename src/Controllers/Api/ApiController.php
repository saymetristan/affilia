<?php

namespace Numok\Controllers\Api;

use Numok\Controllers\Controller;

abstract class ApiController extends Controller
{
    public function __construct()
    {
        $this->handlePreflightRequest();
    }

    protected function handlePreflightRequest(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            $this->respond([], 204);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        if ($input === false || trim($input) === '') {
            return [];
        }

        $decoded = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->respond([
                'error' => 'invalid_json',
                'message' => 'The request body must contain valid JSON.',
            ], 400);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function respond(array $data, int $status = 200): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
        header('Access-Control-Max-Age: 86400');
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    /**
     * @param array<int, string> $allowedMethods
     */
    protected function methodNotAllowed(array $allowedMethods): void
    {
        $this->respond([
            'error' => 'method_not_allowed',
            'message' => 'The requested HTTP method is not supported for this endpoint.',
            'allowed_methods' => $allowedMethods,
        ], 405);
    }

    protected function resourceNotFound(string $message = 'Resource not found.'): void
    {
        $this->respond([
            'error' => 'not_found',
            'message' => $message,
        ], 404);
    }

    /**
     * @param array<string, string> $errors
     */
    protected function validationError(array $errors): void
    {
        $this->respond([
            'error' => 'validation_error',
            'message' => 'The given data was invalid.',
            'errors' => $errors,
        ], 422);
    }
}
