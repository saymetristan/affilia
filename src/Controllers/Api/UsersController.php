<?php

namespace Numok\Controllers\Api;

use Numok\Database\Database;
use Numok\Middleware\ApiMiddleware;

class UsersController extends ApiController
{
    public function __construct()
    {
        ApiMiddleware::handle();
        parent::__construct();
    }

    public function handle(?int $id = null): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($id === null) {
            if ($method === 'GET') {
                $this->index();
                return;
            }

            if ($method === 'POST') {
                $this->store();
                return;
            }

            $this->methodNotAllowed(['GET', 'POST']);
            return;
        }

        if ($method === 'GET') {
            $this->show($id);
            return;
        }

        $this->methodNotAllowed(['GET']);
    }

    private function index(): void
    {
        $users = Database::query(
            'SELECT id, email, name, is_admin, created_at, updated_at FROM users ORDER BY created_at DESC'
        )->fetchAll();

        $users = array_map(fn(array $user): array => $this->transformUser($user), $users);

        $this->respond([
            'data' => $users,
        ]);
    }

    private function show(int $id): void
    {
        $user = Database::query(
            'SELECT id, email, name, is_admin, created_at, updated_at FROM users WHERE id = ? LIMIT 1',
            [$id]
        )->fetch();

        if (!$user) {
            $this->resourceNotFound('User not found.');
        }

        $this->respond([
            'data' => $this->transformUser($user),
        ]);
    }

    private function store(): void
    {
        $input = $this->getJsonInput();

        $email = filter_var((string) ($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $name = trim((string) ($input['name'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $isAdmin = filter_var($input['is_admin'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $errors = [];
        if ($email === false) {
            $errors['email'] = 'A valid email address is required.';
        }
        if ($name === '') {
            $errors['name'] = 'The name field is required.';
        }
        if ($password === '') {
            $errors['password'] = 'The password field is required.';
        }

        if ($errors !== []) {
            $this->validationError($errors);
        }

        $existing = Database::query(
            'SELECT id FROM users WHERE email = ? LIMIT 1',
            [$email]
        )->fetch();

        if ($existing) {
            $this->respond([
                'error' => 'user_exists',
                'message' => 'A user with this email already exists.',
            ], 409);
        }

        $userId = Database::insert('users', [
            'email' => $email,
            'name' => $name,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'is_admin' => $isAdmin ? 1 : 0,
        ]);

        $user = Database::query(
            'SELECT id, email, name, is_admin, created_at, updated_at FROM users WHERE id = ? LIMIT 1',
            [$userId]
        )->fetch();

        $this->respond([
            'data' => $this->transformUser($user),
        ], 201);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function transformUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'is_admin' => (bool) $user['is_admin'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
        ];
    }
}
