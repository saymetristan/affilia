<?php

namespace Numok\Middleware;

class ApiMiddleware {
    /**
     * Handle API authentication and preflight requests.
     *
     * @param array<string> $allowedMethods
     */
    public static function handle(array $allowedMethods = []): void {
        self::sendCorsHeaders();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if ($allowedMethods !== [] && !in_array($method, $allowedMethods, true)) {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'method_not_allowed',
                'message' => 'The requested HTTP method is not supported for this endpoint.',
                'allowed_methods' => $allowedMethods,
            ]);
            exit;
        }

        $token = self::extractToken();
        $validTokens = self::getValidTokens();

        if ($validTokens === []) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'api_token_not_configured',
                'message' => 'API access is disabled because no API tokens are configured.',
            ]);
            exit;
        }

        if ($token === null || !self::isTokenValid($token, $validTokens)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'unauthorized',
                'message' => 'A valid API token is required to access this resource.',
            ]);
            exit;
        }
    }

    private static function sendCorsHeaders(): void {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
        header('Access-Control-Max-Age: 86400');
    }

    private static function extractToken(): ?string {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        if (isset($headers['Authorization'])) {
            $parts = explode(' ', trim((string) $headers['Authorization']));
            if (count($parts) === 2 && strcasecmp($parts[0], 'Bearer') === 0) {
                return $parts[1];
            }
        }

        if (isset($headers['X-API-Key'])) {
            return (string) $headers['X-API-Key'];
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $parts = explode(' ', trim((string) $_SERVER['HTTP_AUTHORIZATION']));
            if (count($parts) === 2 && strcasecmp($parts[0], 'Bearer') === 0) {
                return $parts[1];
            }
        }

        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return (string) $_SERVER['HTTP_X_API_KEY'];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function getValidTokens(): array {
        global $config;

        $tokens = $config['api']['tokens'] ?? [];
        if (!is_array($tokens)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $tokens), static fn($value) => $value !== ''));
    }

    /**
     * @param array<int, string> $validTokens
     */
    private static function isTokenValid(string $token, array $validTokens): bool {
        foreach ($validTokens as $validToken) {
            if ($validToken === '') {
                continue;
            }

            if (str_starts_with($validToken, '$2y$') || str_starts_with($validToken, '$argon2')) {
                if (password_verify($token, $validToken)) {
                    return true;
                }
                continue;
            }

            if (hash_equals($validToken, $token)) {
                return true;
            }
        }

        return false;
    }
}
