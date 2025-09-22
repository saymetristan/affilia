<?php

namespace Numok\Controllers;

use Numok\Database\Database;

class Controller {
    
    protected function handlePreflightRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Access-Control-Max-Age: 86400');
            http_response_code(200);
            exit;
        }
    }

    protected function view(string $template, array $data = []): void {
        // Always include settings in view data
        if (!isset($data['settings'])) {
            $data['settings'] = $this->getSettings();
        }

        extract($data);

        require ROOT_PATH . "/src/Views/layouts/header.php";
        require ROOT_PATH . "/src/Views/{$template}.php";
        require ROOT_PATH . "/src/Views/layouts/footer.php";
    }

    protected function getBaseUrl(?string $appUrl = null): string {
        $appUrl = $appUrl !== null ? trim($appUrl) : '';

        if ($appUrl !== '') {
            $normalized = rtrim($appUrl, '/');
            $parsed = parse_url($normalized);

            if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
                $host = $parsed['host'];
                if (!empty($parsed['port'])) {
                    $host .= ':' . $parsed['port'];
                }

                return sprintf('%s://%s', $parsed['scheme'], $host);
            }

            $host = $parsed['host'] ?? '';
            if ($host === '' && !empty($parsed['path'])) {
                $host = ltrim($parsed['path'], '/');
            }

            if ($host !== '') {
                if (strpos($host, '/') !== false) {
                    $host = substr($host, 0, strpos($host, '/'));
                }

                return sprintf('%s://%s', $this->detectRequestScheme(), $host);
            }
        }

        $scheme = $this->detectRequestScheme();
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host === '' && !empty($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'];
            $port = $_SERVER['SERVER_PORT'] ?? null;
            if ($port && !in_array((int)$port, [80, 443], true) && strpos($host, ':') === false) {
                $host .= ':' . $port;
            }
        }

        if ($host === '') {
            $host = 'localhost';
            $port = $_SERVER['SERVER_PORT'] ?? null;
            if ($port && !in_array((int)$port, [80, 443], true)) {
                $host .= ':' . $port;
            }
        }

        return sprintf('%s://%s', $scheme, rtrim($host, '/'));
    }

    protected function detectRequestScheme(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
            $proto = strtolower(trim($forwarded[0]));
            if ($proto !== '') {
                return $proto;
            }
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }

        if (!empty($_SERVER['REQUEST_SCHEME'])) {
            return strtolower($_SERVER['REQUEST_SCHEME']);
        }

        return 'http';
    }

    protected function getSettings(): array {
        try {
            $stmt = Database::query("SELECT name, value FROM settings");
            $settings = [];
            
            while ($row = $stmt->fetch()) {
                $settings[$row['name']] = $row['value'];
            }

            return $settings;
        } catch (\Exception $e) {
            // Return empty array if database is not available
            return [];
        }
    }

    protected function json(array $data): void {
        // Add CORS headers for all API responses
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
        
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}