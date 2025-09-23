<?php

namespace Numok\Controllers\Api;

use Numok\Database\Database;
use Numok\Middleware\ApiMiddleware;
use Numok\Services\ProgramScriptGenerator;

class ProgramsController extends ApiController
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
        $programs = Database::query(
            'SELECT id, name, description, terms, commission_type, commission_value, cookie_days, is_recurring, reward_days, landing_page, status, created_at, updated_at FROM programs ORDER BY created_at DESC'
        )->fetchAll();

        $programs = array_map(fn(array $program): array => $this->transformProgram($program), $programs);

        $this->respond([
            'data' => $programs,
        ]);
    }

    private function show(int $id): void
    {
        $program = Database::query(
            'SELECT id, name, description, terms, commission_type, commission_value, cookie_days, is_recurring, reward_days, landing_page, status, created_at, updated_at FROM programs WHERE id = ? LIMIT 1',
            [$id]
        )->fetch();

        if (!$program) {
            $this->resourceNotFound('Program not found.');
        }

        $this->respond([
            'data' => $this->transformProgram($program),
        ]);
    }

    private function store(): void
    {
        $input = $this->getJsonInput();

        $name = trim((string) ($input['name'] ?? ''));
        $commissionType = $input['commission_type'] ?? 'percentage';
        $commissionValue = $input['commission_value'] ?? null;
        $cookieDays = $input['cookie_days'] ?? 30;
        $rewardDays = $input['reward_days'] ?? 0;
        $landingPage = trim((string) ($input['landing_page'] ?? ''));
        $isRecurring = filter_var($input['is_recurring'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'The name field is required.';
        }

        if (!in_array($commissionType, ['percentage', 'fixed'], true)) {
            $errors['commission_type'] = 'The commission_type must be either percentage or fixed.';
        }

        if (!is_numeric($commissionValue)) {
            $errors['commission_value'] = 'The commission_value must be numeric.';
        }

        if (!is_numeric($cookieDays) || (int) $cookieDays < 1) {
            $errors['cookie_days'] = 'The cookie_days must be a positive integer.';
        }

        if (!is_numeric($rewardDays) || (int) $rewardDays < 0) {
            $errors['reward_days'] = 'The reward_days must be zero or a positive integer.';
        }

        if ($landingPage === '' || filter_var($landingPage, FILTER_VALIDATE_URL) === false) {
            $errors['landing_page'] = 'A valid landing_page URL is required.';
        }

        if ($errors !== []) {
            $this->validationError($errors);
        }

        $data = [
            'name' => $name,
            'description' => $input['description'] ?? null,
            'terms' => $input['terms'] ?? null,
            'commission_type' => $commissionType,
            'commission_value' => round((float) $commissionValue, 2),
            'cookie_days' => (int) $cookieDays,
            'is_recurring' => $isRecurring ? 1 : 0,
            'reward_days' => (int) $rewardDays,
            'landing_page' => $landingPage,
            'status' => 'active',
        ];

        $programId = Database::insert('programs', $data);

        $program = Database::query(
            'SELECT * FROM programs WHERE id = ? LIMIT 1',
            [$programId]
        )->fetch();

        $settings = $this->getSettings();
        $baseUrl = $this->getBaseUrl($settings['app_url'] ?? null);
        ProgramScriptGenerator::generate($program, $baseUrl);

        $this->respond([
            'data' => $this->transformProgram($program),
        ], 201);
    }

    /**
     * @param array<string, mixed> $program
     * @return array<string, mixed>
     */
    private function transformProgram(array $program): array
    {
        return [
            'id' => (int) $program['id'],
            'name' => $program['name'],
            'description' => $program['description'],
            'terms' => $program['terms'],
            'commission_type' => $program['commission_type'],
            'commission_value' => (float) $program['commission_value'],
            'cookie_days' => (int) $program['cookie_days'],
            'is_recurring' => (bool) $program['is_recurring'],
            'reward_days' => (int) $program['reward_days'],
            'landing_page' => $program['landing_page'],
            'status' => $program['status'],
            'created_at' => $program['created_at'],
            'updated_at' => $program['updated_at'],
        ];
    }
}
