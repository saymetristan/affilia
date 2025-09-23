<?php

namespace Numok\Services;

class ProgramScriptGenerator {
    public static function generate(array $program, string $baseUrl): bool {
        $baseUrl = rtrim($baseUrl, '/');
        $programId = (int)$program['id'];
        $cookieDays = (int)$program['cookie_days'];
        $baseUrlLiteral = json_encode($baseUrl, JSON_UNESCAPED_SLASHES);

        $script = <<<JAVASCRIPT
(function() {
    const COOKIE_NAME = 'numok_tracking';
    const IMPRESSION_KEY_PREFIX = 'numok_impression_';
    const COOKIE_DAYS = {$cookieDays};
    const PROGRAM_ID = {$programId};
    const API_BASE = {$baseUrlLiteral};

    class NumokTracker {
        constructor() {
            this.init();
        }

        async init() {
            const urlParams = new URLSearchParams(window.location.search);
            let trackingData = this.getTrackingData();

            if (urlParams.has('via')) {
                trackingData = {
                    tracking_code: urlParams.get('via'),
                    sid: urlParams.get('sid') || null,
                    sid2: urlParams.get('sid2') || null,
                    sid3: urlParams.get('sid3') || null,
                    referrer: document.referrer || null,
                    landing_page: window.location.href,
                    timestamp: new Date().toISOString()
                };

                trackingData = this.saveTrackingData(trackingData);
            }

            if (trackingData && trackingData.tracking_code) {
                this.trackImpression(trackingData.tracking_code).catch(console.error);
            }
        }

        saveTrackingData(data) {
            const storedData = {
                tracking_code: data.tracking_code,
                sid: data.sid || null,
                sid2: data.sid2 || null,
                sid3: data.sid3 || null,
                referrer: data.referrer || null,
                landing_page: data.landing_page || window.location.href,
                timestamp: data.timestamp || new Date().toISOString()
            };

            const expires = new Date();
            expires.setDate(expires.getDate() + COOKIE_DAYS);
            const cookieParts = [
                COOKIE_NAME + '=' + encodeURIComponent(JSON.stringify(storedData)),
                'expires=' + expires.toUTCString(),
                'path=/',
                'SameSite=Lax'
            ];
            document.cookie = cookieParts.join(';');

            this.trackClick(storedData).catch(console.error);

            return storedData;
        }

        async trackClick(data) {
            try {
                const trackingEnabled = await this.checkTrackingEnabled();
                if (!trackingEnabled) return;

                await fetch(API_BASE + '/api/tracking/click', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            } catch (error) {
                console.error('Click tracking failed:', error);
            }
        }

        async checkTrackingEnabled() {
            try {
                const response = await fetch(API_BASE + '/api/tracking/config/' + PROGRAM_ID);
                const config = await response.json();
                return Boolean(config.track_clicks);
            } catch (error) {
                console.error('Unable to determine click tracking status:', error);
                return false;
            }
        }

        async trackImpression(trackingCode) {
            if (!trackingCode) return;

            const markerKey = IMPRESSION_KEY_PREFIX + trackingCode;
            if (this.hasRecentImpression(markerKey)) {
                return;
            }

            try {
                await fetch(API_BASE + '/api/tracking/impression', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        program_id: PROGRAM_ID,
                        tracking_code: trackingCode,
                        url: window.location.href
                    })
                });

                this.setImpressionMarker(markerKey);
            } catch (error) {
                console.error('Impression tracking failed:', error);
            }
        }

        setImpressionMarker(key) {
            const expires = new Date(Date.now() + 24 * 60 * 60 * 1000);
            const markerParts = [
                key + '=' + Date.now(),
                'expires=' + expires.toUTCString(),
                'path=/',
                'SameSite=Lax'
            ];
            document.cookie = markerParts.join(';');
        }

        hasRecentImpression(key) {
            const marker = this.getCookie(key);
            if (!marker) {
                return false;
            }

            const timestamp = parseInt(marker, 10);
            if (Number.isNaN(timestamp)) {
                return true;
            }

            const elapsed = Date.now() - timestamp;
            return elapsed < 24 * 60 * 60 * 1000;
        }

        getStripeMetadata() {
            const data = this.getTrackingData();
            if (!data) return {};

            return {
                numok_tracking_code: data.tracking_code,
                ...(data.sid ? { numok_sid: data.sid } : {}),
                ...(data.sid2 ? { numok_sid2: data.sid2 } : {}),
                ...(data.sid3 ? { numok_sid3: data.sid3 } : {})
            };
        }

        getTrackingData() {
            const cookie = this.getCookie(COOKIE_NAME);
            if (!cookie) return null;

            try {
                return JSON.parse(decodeURIComponent(cookie));
            } catch (error) {
                try {
                    return JSON.parse(cookie);
                } catch (innerError) {
                    console.error('Failed to parse tracking cookie:', innerError);
                    return null;
                }
            }
        }

        getCookie(name) {
            const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? match[2] : null;
        }

        hasTracking() {
            return !!this.getTrackingData();
        }
    }

    window.numok = new NumokTracker();
})();
JAVASCRIPT;

        $minified = self::minifyJS($script);

        $dir = ROOT_PATH . '/public/tracking';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents("{$dir}/program-{$program['id']}.js", $minified) !== false;
    }

    private static function minifyJS(string $js): string {
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
        $js = str_replace(["\r\n", "\r", "\n", "\t"], '', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        return trim($js);
    }
}