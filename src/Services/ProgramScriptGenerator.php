<?php

namespace Numok\Services;

class ProgramScriptGenerator {
    public static function generate(array $program, string $baseUrl): bool {
        // Ensure base URL doesn't end with a slash
        $baseUrl = rtrim($baseUrl, '/');
        
        $script = <<<JAVASCRIPT
(function() {
    const COOKIE_NAME = 'numok_tracking';
    const IMPRESSION_COOKIE_PREFIX = 'numok_impression_';
    const COOKIE_DAYS = {$program['cookie_days']};
    const PROGRAM_ID = {$program['id']};
    const API_BASE_URL = '{$baseUrl}';

    class NumokTracker {
        constructor() {
            this.init();
        }

        init() {
            const urlParams = new URLSearchParams(window.location.search);
            let trackingCode = null;

            if (urlParams.has('via')) {
                const trackingData = {
                    tracking_code: urlParams.get('via'),
                    sid: urlParams.get('sid') || null,
                    sid2: urlParams.get('sid2') || null,
                    sid3: urlParams.get('sid3') || null,
                    referrer: document.referrer || null,
                    timestamp: new Date().toISOString()
                };

                trackingCode = trackingData.tracking_code;
                this.saveTrackingData(trackingData);
            } else {
                const existingTracking = this.getTrackingData();
                if (existingTracking?.tracking_code) {
                    trackingCode = existingTracking.tracking_code;
                }
            }

            if (trackingCode) {
                this.trackImpression(trackingCode).catch(console.error);
            }
        }

        saveTrackingData(data) {
            const expires = new Date();
            expires.setDate(expires.getDate() + COOKIE_DAYS);
            document.cookie = `\${COOKIE_NAME}=\${JSON.stringify(data)};expires=\${expires.toUTCString()};path=/`;
            this.trackClick(data).catch(console.error);
        }

        async trackClick(data) {
            try {
                const trackingEnabled = await this.checkTrackingEnabled();
                if (!trackingEnabled) return;

                await fetch(`\${API_BASE_URL}/api/tracking/click`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            } catch (error) {
                console.error('Click tracking failed:', error);
            }
        }

        async trackImpression(trackingCode) {
            try {
                const impressionKey = this.getImpressionKey(trackingCode);
                if (this.hasImpressionMark(impressionKey)) return;

                const response = await fetch(`\${API_BASE_URL}/api/tracking/impression`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        program_id: PROGRAM_ID,
                        tracking_code: trackingCode,
                        url: window.location.href
                    })
                });

                if (!response.ok) {
                    throw new Error('Impression tracking failed');
                }

                this.setImpressionMark(impressionKey);
            } catch (error) {
                console.error('Impression tracking failed:', error);
            }
        }

        async checkTrackingEnabled() {
            try {
                const response = await fetch(`\${API_BASE_URL}/api/tracking/config/\${PROGRAM_ID}`);
                const config = await response.json();
                return config.track_clicks || false;
            } catch {
                return false;
            }
        }

        getImpressionKey(trackingCode) {
            const encodedCode = encodeURIComponent(trackingCode);
            return `\${IMPRESSION_COOKIE_PREFIX}\${PROGRAM_ID}_\${encodedCode}`;
        }

        hasImpressionMark(key) {
            try {
                if (window.sessionStorage && window.sessionStorage.getItem(key)) {
                    return true;
                }
            } catch (error) {
                // Access to sessionStorage can fail in private modes
            }

            return !!this.getCookie(key);
        }

        setImpressionMark(key) {
            const expires = new Date(Date.now() + 24 * 60 * 60 * 1000);
            document.cookie = `\${key}=1;expires=\${expires.toUTCString()};path=/`;

            try {
                if (window.sessionStorage) {
                    window.sessionStorage.setItem(key, Date.now().toString());
                }
            } catch (error) {
                // sessionStorage may be unavailable
            }
        }

        getStripeMetadata() {
            const data = this.getTrackingData();
            if (!data) return {};

            return {
                numok_tracking_code: data.tracking_code,
                ...data.sid && { numok_sid: data.sid },
                ...data.sid2 && { numok_sid2: data.sid2 },
                ...data.sid3 && { numok_sid3: data.sid3 }
            };
        }

        getTrackingData() {
            const cookie = this.getCookie(COOKIE_NAME);
            if (!cookie) return null;

            try {
                return JSON.parse(cookie);
            } catch {
                return null;
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