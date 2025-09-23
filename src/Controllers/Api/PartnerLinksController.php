<?php

namespace Numok\Controllers\Api;

use Numok\Database\Database;
use Numok\Middleware\ApiMiddleware;

class PartnerLinksController extends ApiController
{
    public function __construct()
    {
        ApiMiddleware::handle();
        parent::__construct();
    }

    public function handle(int $partnerId): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $partner = Database::query(
            'SELECT id, email, company_name, status FROM partners WHERE id = ? LIMIT 1',
            [$partnerId]
        )->fetch();

        if (!$partner) {
            $this->resourceNotFound('Partner not found.');
        }

        $links = Database::query(
            'SELECT pp.id, pp.tracking_code, pp.status, pp.postback_url, pp.created_at, pp.updated_at, p.id AS program_id, p.name AS program_name, p.landing_page
             FROM partner_programs pp
             INNER JOIN programs p ON p.id = pp.program_id
             WHERE pp.partner_id = ?
             ORDER BY pp.created_at DESC',
            [$partnerId]
        )->fetchAll();

        $settings = $this->getSettings();
        $baseUrl = $this->getBaseUrl($settings['app_url'] ?? null);

        $links = array_map(function (array $link) use ($baseUrl): array {
            $landingPage = $link['landing_page'] ?: $baseUrl;
            $trackingUrl = rtrim($landingPage, '?&');
            $separator = str_contains($landingPage, '?') ? '&' : '?';
            $trackingUrl .= $separator . 'via=' . urlencode($link['tracking_code']);

            return [
                'partner_program_id' => (int) $link['id'],
                'program_id' => (int) $link['program_id'],
                'program_name' => $link['program_name'],
                'tracking_code' => $link['tracking_code'],
                'tracking_url' => $trackingUrl,
                'status' => $link['status'],
                'postback_url' => $link['postback_url'],
                'created_at' => $link['created_at'],
                'updated_at' => $link['updated_at'],
            ];
        }, $links);

        $this->respond([
            'partner' => [
                'id' => (int) $partner['id'],
                'email' => $partner['email'],
                'company_name' => $partner['company_name'],
                'status' => $partner['status'],
            ],
            'links' => $links,
        ]);
    }
}
