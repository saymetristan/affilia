<?php

namespace Numok\Controllers;

use Numok\Database\Database;

class HealthController extends Controller {
    
    public function health(): void {
        $response = [
            'status' => 'ok',
            'timestamp' => date('c'),
            'services' => []
        ];

        // Test database connection
        try {
            Database::query("SELECT 1")->fetch();
            $response['services']['database'] = 'healthy';
        } catch (\Exception $e) {
            $response['services']['database'] = 'unhealthy';
            $response['status'] = 'error';
            http_response_code(503);
        }

        // Test file system permissions
        $testPath = ROOT_PATH . '/public/tracking';
        if (is_writable($testPath)) {
            $response['services']['filesystem'] = 'healthy';
        } else {
            $response['services']['filesystem'] = 'unhealthy';
            $response['status'] = 'warning';
        }

        // Add application info
        $response['app'] = [
            'name' => 'Numok Affiliates',
            'version' => '1.0.0',
            'environment' => getenv('APP_DEBUG') ? 'development' : 'production'
        ];

        $this->json($response);
    }

    public function ready(): void {
        // Simple ready check for Railway
        try {
            Database::query("SELECT COUNT(*) FROM users")->fetch();
            $this->json(['status' => 'ready', 'timestamp' => date('c')]);
        } catch (\Exception $e) {
            http_response_code(503);
            $this->json(['status' => 'not ready', 'error' => 'Database not available']);
        }
    }
}