<?php

namespace Numok\Controllers;

use Numok\Database\Database;
use Numok\Middleware\AuthMiddleware;

class SettingsController extends Controller {
    private string $stripeTestEndpoint = 'https://api.stripe.com/v1/customers';
    public function __construct() {
        AuthMiddleware::adminOnly();
    }

    public function index(): void {
        $settings = $this->getAllSettings();
        
        $this->view('settings/index', [
            'title' => 'Settings - ' . ($settings['custom_app_name'] ?? 'Numok'),
            'settings' => $settings,
            'success' => $_SESSION['settings_success'] ?? null,
            'error' => $_SESSION['settings_error'] ?? null
        ]);
        
        // Clear flash messages
        unset($_SESSION['settings_success'], $_SESSION['settings_error']);
    }

    public function update(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/settings');
            exit;
        }

        try {
            $settingsToUpdate = [];

            if (array_key_exists('app_name', $_POST)) {
                $appName = trim((string)$_POST['app_name']);
                $settingsToUpdate['app_name'] = $appName !== '' ? $appName : 'Numok';
            }

            if (array_key_exists('partner_welcome_message', $_POST)) {
                $settingsToUpdate['partner_welcome_message'] = trim((string)$_POST['partner_welcome_message']);
            }

            if (array_key_exists('stripe_secret_key', $_POST)) {
                $settingsToUpdate['stripe_secret_key'] = trim((string)$_POST['stripe_secret_key']);
            }

            if (array_key_exists('stripe_webhook_secret', $_POST)) {
                $settingsToUpdate['stripe_webhook_secret'] = trim((string)$_POST['stripe_webhook_secret']);
            }

            if (array_key_exists('app_url', $_POST)) {
                $appUrl = trim((string)$_POST['app_url']);

                if ($appUrl !== '' && !filter_var($appUrl, FILTER_VALIDATE_URL)) {
                    throw new \InvalidArgumentException('Please provide a valid application URL.');
                }

                $settingsToUpdate['app_url'] = rtrim($appUrl, '/');
            }

            if (array_key_exists('click_tracking_enabled', $_POST)) {
                $settingsToUpdate['click_tracking_enabled'] = $_POST['click_tracking_enabled'] === '1' ? '1' : '0';
            }

            if (empty($settingsToUpdate)) {
                throw new \RuntimeException('No settings were provided for update.');
            }

            Database::transaction(function() use ($settingsToUpdate) {
                foreach ($settingsToUpdate as $key => $value) {
                    Database::query(
                        "INSERT INTO settings (name, value)
                         VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE value = VALUES(value)",
                        [$key, $value]
                    );
                }
            });

            $_SESSION['settings_success'] = 'Settings updated successfully.';
        } catch (\InvalidArgumentException $e) {
            $_SESSION['settings_error'] = $e->getMessage();
        } catch (\Exception $e) {
            $_SESSION['settings_error'] = 'Failed to update settings. Please try again.';
        }

        header('Location: /admin/settings');
        exit;
    }

    public function testConnection(): void {
        header('Content-Type: application/json');

        $settings = $this->getAllSettings();
        $response = ['success' => false, 'messages' => []];

        // Test Stripe API Key
        if (!empty($settings['stripe_secret_key'])) {
            $ch = curl_init($this->stripeTestEndpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $settings['stripe_secret_key'],
                    'Stripe-Version: 2023-10-16'
                ]
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $response['messages'][] = [
                    'type' => 'success',
                    'text' => 'Successfully connected to Stripe API'
                ];
                $response['success'] = true;
            } elseif ($httpCode === 401) {
                $response['messages'][] = [
                    'type' => 'error',
                    'text' => 'Invalid Stripe API key'
                ];
            } else {
                $response['messages'][] = [
                    'type' => 'error',
                    'text' => 'Could not connect to Stripe API'
                ];
            }
        } else {
            $response['messages'][] = [
                'type' => 'warning',
                'text' => 'Stripe API key not configured'
            ];
        }

        // Test Webhook Secret
        if (!empty($settings['stripe_webhook_secret'])) {
            // Create a test signature using the webhook secret
            $timestamp = time();
            $payload = json_encode(['type' => 'test']);
            $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $settings['stripe_webhook_secret']);
            
            if ($signature) {
                $response['messages'][] = [
                    'type' => 'success',
                    'text' => 'Webhook secret is properly formatted'
                ];
                if (!$response['success']) {
                    $response['success'] = true;
                }
            }
        } else {
            $response['messages'][] = [
                'type' => 'warning',
                'text' => 'Webhook secret not configured'
            ];
        }

        echo json_encode($response);
        exit;
    }

    private function getAllSettings(): array {
        $stmt = Database::query("SELECT name, value FROM settings");
        $settings = [];
        
        while ($row = $stmt->fetch()) {
            $settings[$row['name']] = $row['value'];
        }

        return $settings;
    }

    public function updateProfile(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/settings');
            exit;
        }
    
        $userId = $_SESSION['user_id'];
        $currentPassword = $_POST['current_password'] ?? '';
        $newEmail = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
    
        try {
            // Verify current user
            $user = Database::query(
                "SELECT * FROM users WHERE id = ? LIMIT 1",
                [$userId]
            )->fetch();
    
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $_SESSION['settings_error'] = 'Current password is incorrect';
                header('Location: /admin/settings');
                exit;
            }
    
            $updates = [];
            $params = [];
    
            // Handle email update
            if ($newEmail && $newEmail !== $user['email']) {
                // Check if email is already taken
                $existing = Database::query(
                    "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1",
                    [$newEmail, $userId]
                )->fetch();
    
                if ($existing) {
                    $_SESSION['settings_error'] = 'Email address is already in use';
                    header('Location: /admin/settings');
                    exit;
                }
    
                $updates[] = "email = ?";
                $params[] = $newEmail;
            }
    
            // Handle password update
            if ($newPassword) {
                if (strlen($newPassword) < 8) {
                    $_SESSION['settings_error'] = 'New password must be at least 8 characters long';
                    header('Location: /admin/settings');
                    exit;
                }
    
                if ($newPassword !== $confirmPassword) {
                    $_SESSION['settings_error'] = 'New passwords do not match';
                    header('Location: /admin/settings');
                    exit;
                }
    
                $updates[] = "password = ?";
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
    
            // If there are updates to make
            if (!empty($updates)) {
                $params[] = $userId;
                Database::query(
                    "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?",
                    $params
                );
    
                // Update session if email changed
                if ($newEmail && $newEmail !== $user['email']) {
                    $_SESSION['user_email'] = $newEmail;
                }
    
                $_SESSION['settings_success'] = 'Profile updated successfully';
            }
    
        } catch (\Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $_SESSION['settings_error'] = 'Failed to update profile. Please try again.';
        }
    
        header('Location: /admin/settings');
        exit;
    }

    public function updateBranding(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/settings');
            exit;
        }

        try {
            Database::transaction(function() {
                $settings = [];
                
                // Handle custom app name
                if (isset($_POST['custom_app_name'])) {
                    $settings['custom_app_name'] = trim($_POST['custom_app_name']);
                }

                // Handle logo upload
                if (isset($_FILES['custom_logo']) && $_FILES['custom_logo']['error'] === UPLOAD_ERR_OK) {
                    $uploadedFile = $_FILES['custom_logo'];
                    
                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
                    if (!in_array($uploadedFile['type'], $allowedTypes)) {
                        throw new \Exception('Invalid file type. Please upload a PNG, JPG, SVG, or WebP image.');
                    }
                    
                    // Validate file size (max 5MB)
                    if ($uploadedFile['size'] > 5 * 1024 * 1024) {
                        throw new \Exception('File size too large. Please upload an image smaller than 5MB.');
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
                    $uploadDir = ROOT_PATH . '/public/assets/uploads';
                    $uploadPath = $uploadDir . '/' . $filename;
                    
                    // Create uploads directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Move uploaded file
                    if (!move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
                        throw new \Exception('Failed to upload logo. Please try again.');
                    }
                    
                    // Delete old logo if exists
                    $currentSettings = $this->getAllSettings();
                    if (!empty($currentSettings['custom_logo'])) {
                        $oldLogoPath = ROOT_PATH . '/public/assets/uploads/' . $currentSettings['custom_logo'];
                        if (file_exists($oldLogoPath)) {
                            unlink($oldLogoPath);
                        }
                    }
                    
                    $settings['custom_logo'] = $filename;
                }

                // Save settings to database
                foreach ($settings as $key => $value) {
                    Database::query(
                        "INSERT INTO settings (name, value) 
                         VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE value = VALUES(value)",
                        [$key, $value]
                    );
                }
            });

            $_SESSION['settings_success'] = 'Branding updated successfully.';
        } catch (\Exception $e) {
            $_SESSION['settings_error'] = $e->getMessage();
        }

        header('Location: /admin/settings');
        exit;
    }

    public function resetBranding(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/settings');
            exit;
        }

        try {
            Database::transaction(function() {
                // Get current settings to delete old logo file
                $currentSettings = $this->getAllSettings();
                
                // Delete old logo file if exists
                if (!empty($currentSettings['custom_logo'])) {
                    $oldLogoPath = ROOT_PATH . '/public/assets/uploads/' . $currentSettings['custom_logo'];
                    if (file_exists($oldLogoPath)) {
                        unlink($oldLogoPath);
                    }
                }
                
                // Remove custom branding settings from database
                Database::query("DELETE FROM settings WHERE name IN ('custom_app_name', 'custom_logo')");
            });

            $_SESSION['settings_success'] = 'Branding reset to Numok successfully.';
        } catch (\Exception $e) {
            $_SESSION['settings_error'] = 'Failed to reset branding. Please try again.';
        }

        header('Location: /admin/settings');
        exit;
    }
}