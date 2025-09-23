<?php

namespace Numok\Controllers\Api;

use Numok\Database\Database;
use Numok\Middleware\ApiMiddleware;

class PartnersController extends ApiController
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
        $partners = Database::query(
            'SELECT id, email, company_name, contact_name, status, payment_email, created_at, updated_at FROM partners ORDER BY created_at DESC'
        )->fetchAll();

        $partners = array_map(fn(array $partner): array => $this->transformPartner($partner), $partners);

        $this->respond([
            'data' => $partners,
        ]);
    }

    private function show(int $id): void
    {
        $partner = Database::query(
            'SELECT id, email, company_name, contact_name, status, payment_email, created_at, updated_at FROM partners WHERE id = ? LIMIT 1',
            [$id]
        )->fetch();

        if (!$partner) {
            $this->resourceNotFound('Partner not found.');
        }

        $this->respond([
            'data' => $this->transformPartner($partner),
        ]);
    }

    private function store(): void
    {
        $input = $this->getJsonInput();

        $email = filter_var((string) ($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $companyName = trim((string) ($input['company_name'] ?? ''));
        $contactName = trim((string) ($input['contact_name'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $paymentEmailRaw = $input['payment_email'] ?? null;
        $status = $input['status'] ?? 'pending';

        $errors = [];
        if ($email === false) {
            $errors['email'] = 'A valid email address is required.';
        }
        if ($companyName === '') {
            $errors['company_name'] = 'The company_name field is required.';
        }
        if ($contactName === '') {
            $errors['contact_name'] = 'The contact_name field is required.';
        }
        if ($password === '') {
            $errors['password'] = 'The password field is required.';
        }
        if (!in_array($status, ['pending', 'active', 'rejected', 'suspended'], true)) {
            $errors['status'] = 'The status must be one of: pending, active, rejected, suspended.';
        }

        if ($paymentEmailRaw !== null && filter_var((string) $paymentEmailRaw, FILTER_VALIDATE_EMAIL) === false) {
            $errors['payment_email'] = 'The payment_email must be a valid email address when provided.';
        }

        if ($errors !== []) {
            $this->validationError($errors);
        }

        $existing = Database::query(
            'SELECT id FROM partners WHERE email = ? LIMIT 1',
            [$email]
        )->fetch();

        if ($existing) {
            $this->respond([
                'error' => 'partner_exists',
                'message' => 'A partner with this email already exists.',
            ], 409);
        }

        $partnerId = Database::insert('partners', [
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'company_name' => $companyName,
            'contact_name' => $contactName,
            'status' => $status,
            'payment_email' => $paymentEmailRaw !== null ? (string) $paymentEmailRaw : null,
        ]);

        $partner = Database::query(
            'SELECT id, email, company_name, contact_name, status, payment_email, created_at, updated_at FROM partners WHERE id = ? LIMIT 1',
            [$partnerId]
        )->fetch();

        $this->respond([
            'data' => $this->transformPartner($partner),
        ], 201);
    }

    /**
     * @param array<string, mixed> $partner
     * @return array<string, mixed>
     */
    private function transformPartner(array $partner): array
    {
        return [
            'id' => (int) $partner['id'],
            'email' => $partner['email'],
            'company_name' => $partner['company_name'],
            'contact_name' => $partner['contact_name'],
            'status' => $partner['status'],
            'payment_email' => $partner['payment_email'],
            'created_at' => $partner['created_at'],
            'updated_at' => $partner['updated_at'],
        ];
    }
}
