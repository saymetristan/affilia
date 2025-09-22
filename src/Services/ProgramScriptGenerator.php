<?php

namespace Numok\Services;

class ProgramScriptGenerator {
    public static function generate(array $program, string $domain): bool {
        // Ensure domain doesn't end with a slash
        $domain = rtrim($domain, '/');
        
        $script = <<<JAVASCRIPT
(function() {
    const COOKIE_NAME = 'numok_tracking';
    const COOKIE_DAYS = {$program['cookie_days']};
    const PROGRAM_ID = {$program['id']};
    const API_DOMAIN = '{$domain}';
    const IMPRESSION_COOKIE_PREFIX = 'numok_impression_';
    const IMPRESSION_COOKIE_HOURS = 24;

    class NumokTracker {
        constructor() {
            this.init();
        }

        init() {
            const urlParams = new URLSearchParams(window.location.search);
            let trackingData = this.getTrackingData();

            if (urlParams.has('via')) {
                trackingData = {
                    tracking_code: urlParams.get('via'),
                    sid: urlParams.get('sid') || null,
                    sid2: urlParams.get('sid2') || null,
                    sid3: urlParams.get('sid3') || null,
                    referrer: document.referrer || null,
                    timestamp: new Date().toISOString()
                };

                this.saveTrackingData(trackingData);
            }

            if (trackingData?.tracking_code) {
                this.trackImpression(trackingData.tracking_code).catch(console.error);
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

                await fetch(`https://\${API_DOMAIN}/api/tracking/click`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            } catch (error) {
                console.error('Click tracking failed:', error);
            }
        }

        async trackImpression(trackingCode) {
            const impressionCookie = this.getImpressionCookieName(trackingCode);
            if (this.getCookie(impressionCookie)) {
                return;
            }

            try {
                await fetch(`https://\${API_DOMAIN}/api/tracking/impression`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        program_id: PROGRAM_ID,
                        tracking_code: trackingCode,
                        url: window.location.href
                    })
                });

                const expires = new Date();
                expires.setTime(expires.getTime() + IMPRESSION_COOKIE_HOURS * 60 * 60 * 1000);
                document.cookie = `\${impressionCookie}=1;expires=\${expires.toUTCString()};path=/`;
            } catch (error) {
                console.error('Impression tracking failed:', error);
            }
        }

        async checkTrackingEnabled() {
            try {
                const response = await fetch(`https://\${API_DOMAIN}/api/tracking/config/\${PROGRAM_ID}`);
                const config = await response.json();
                return config.track_clicks || false;
            } catch {
                return false;
            }
        }

        getImpressionCookieName(trackingCode) {
            return IMPRESSION_COOKIE_PREFIX + trackingCode;
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