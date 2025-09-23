<?php

namespace Numok\Controllers;

use Numok\Database\Database;

class Controller {

    protected function detectRequestScheme(): string {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
            return strtolower(trim($forwarded[0])) === 'https' ? 'https' : 'http';
        }

        if (!empty($_SERVER['REQUEST_SCHEME'])) {
            return strtolower($_SERVER['REQUEST_SCHEME']) === 'https' ? 'https' : 'http';
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }

        return 'http';
    }

    protected function resolveAppBaseUrl(?array $settings = null): string {
        $settings = $settings ?? $this->getSettings();
        $rawAppUrl = trim($settings['app_url'] ?? '');

        if ($rawAppUrl !== '') {
            $normalized = $this->normalizeUrl($rawAppUrl);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $scheme = $this->detectRequestScheme();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return sprintf('%s://%s', $scheme, $host);
    }

    protected function normalizeUrl(string $url, ?string $fallbackScheme = null): ?string {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $hasScheme = (bool)preg_match('#^https?://#i', $url);
        $scheme = $fallbackScheme ?? $this->detectRequestScheme();

        if (!$hasScheme) {
            $url = sprintf('%s://%s', $scheme, ltrim($url, '/'));
        }

        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if ($validated === false) {
            return null;
        }

        return rtrim($validated, '/');
    }

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