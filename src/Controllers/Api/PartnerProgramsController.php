<?php

namespace Numok\Controllers\Api;

use Numok\Database\Database;
use Numok\Middleware\ApiMiddleware;

class PartnerProgramsController extends ApiController
{
    public function __construct()
    {
        ApiMiddleware::handle();
        parent::__construct();
    }

    public function handle(int $partnerId): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            $this->index($partnerId);
            return;
        }

        if ($method === 'POST') {
            $this->store($partnerId);
            return;
        }

        $this->methodNotAllowed(['GET', 'POST']);
    }

    private function index(int $partnerId): void
    {
        $partner = Database::query(
            'SELECT id FROM partners WHERE id = ? LIMIT 1',
            [$partnerId]
        )->fetch();

        if (!$partner) {
            $this->resourceNotFound('Partner not found.');
        }

        $programs = Database::query(
            'SELECT pp.id AS partner_program_id, pp.tracking_code, pp.status, pp.postback_url, pp.created_at, pp.updated_at,
                    p.id AS program_id, p.name AS program_name, p.landing_page
             FROM partner_programs pp
             INNER JOIN programs p ON p.id = pp.program_id
             WHERE pp.partner_id = ?
             ORDER BY pp.created_at DESC',
            [$partnerId]
        )->fetchAll();

        $settings = $this->getSettings();
        $baseUrl = $this->getBaseUrl($settings['app_url'] ?? null);

        $programs = array_map(function (array $program) use ($baseUrl): array {
            $landingPage = $program['landing_page'] ?: $baseUrl;
            $trackingUrl = rtrim($landingPage, '?&');
            $separator = str_contains($landingPage, '?') ? '&' : '?';
            $trackingUrl .= $separator . 'via=' . urlencode($program['tracking_code']);

            return [
                'partner_program_id' => (int) $program['partner_program_id'],
                'program_id' => (int) $program['program_id'],
                'program_name' => $program['program_name'],
                'tracking_code' => $program['tracking_code'],
                'tracking_url' => $trackingUrl,
                'status' => $program['status'],
                'postback_url' => $program['postback_url'],
                'created_at' => $program['created_at'],
                'updated_at' => $program['updated_at'],
            ];
        }, $programs);

        $this->respond([
            'data' => $programs,
        ]);
    }

    private function store(int $partnerId): void
    {
        $partner = Database::query(
            'SELECT id FROM partners WHERE id = ? LIMIT 1',
            [$partnerId]
        )->fetch();

        if (!$partner) {
            $this->resourceNotFound('Partner not found.');
        }

        $input = $this->getJsonInput();
        $programId = $input['program_id'] ?? null;
        $postbackUrl = isset($input['postback_url']) ? trim((string) $input['postback_url']) : null;
        $status = $input['status'] ?? 'active';

        $errors = [];
        if (!is_numeric($programId)) {
            $errors['program_id'] = 'The program_id field is required.';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'The status must be either active or inactive.';
        }
        if ($postbackUrl !== null && $postbackUrl !== '' && filter_var($postbackUrl, FILTER_VALIDATE_URL) === false) {
            $errors['postback_url'] = 'The postback_url must be a valid URL when provided.';
        }

        if ($errors !== []) {
            $this->validationError($errors);
        }

        $program = Database::query(
            'SELECT id, landing_page FROM programs WHERE id = ? AND status = "active" LIMIT 1',
            [$programId]
        )->fetch();

        if (!$program) {
            $this->respond([
                'error' => 'invalid_program',
                'message' => 'The specified program is not available.',
            ], 409);
        }

        $existing = Database::query(
            'SELECT id, tracking_code, status, postback_url, created_at, updated_at FROM partner_programs WHERE partner_id = ? AND program_id = ? LIMIT 1',
            [$partnerId, $programId]
        )->fetch();

        if ($existing) {
            $updateData = [];
            if ($postbackUrl !== null) {
                $updateData['postback_url'] = $postbackUrl !== '' ? $postbackUrl : null;
            }
            if ($status !== $existing['status']) {
                $updateData['status'] = $status;
            }

            if ($updateData !== []) {
                Database::update('partner_programs', $updateData, 'id = ?', [$existing['id']]);
            }

            $this->respond([
                'data' => [
                    'partner_program_id' => (int) $existing['id'],
                    'program_id' => (int) $programId,
                    'tracking_code' => $existing['tracking_code'],
                    'status' => $status,
                    'postback_url' => $postbackUrl !== null ? ($postbackUrl !== '' ? $postbackUrl : null) : $existing['postback_url'],
                    'created_at' => $existing['created_at'],
                    'updated_at' => $existing['updated_at'],
                ],
                'message' => 'Partner is already assigned to this program. Existing assignment returned.',
            ]);
        }

        $trackingCode = $this->generateTrackingCode();

        $partnerProgramId = Database::insert('partner_programs', [
            'partner_id' => $partnerId,
            'program_id' => $programId,
            'tracking_code' => $trackingCode,
            'postback_url' => $postbackUrl !== null && $postbackUrl !== '' ? $postbackUrl : null,
            'status' => $status,
        ]);

        $assignment = Database::query(
            'SELECT id, tracking_code, status, postback_url, created_at, updated_at FROM partner_programs WHERE id = ? LIMIT 1',
            [$partnerProgramId]
        )->fetch();

        $this->respond([
            'data' => [
                'partner_program_id' => (int) $assignment['id'],
                'program_id' => (int) $programId,
                'tracking_code' => $assignment['tracking_code'],
                'status' => $assignment['status'],
                'postback_url' => $assignment['postback_url'],
                'created_at' => $assignment['created_at'],
                'updated_at' => $assignment['updated_at'],
            ],
        ], 201);
    }

    private function generateTrackingCode(): string
    {
        do {
            $code = bin2hex(random_bytes(8));
            $exists = Database::query(
                'SELECT id FROM partner_programs WHERE tracking_code = ? LIMIT 1',
                [$code]
            )->fetch();
        } while ($exists);

        return $code;
    }
}
