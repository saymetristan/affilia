const API_DOMAIN = window.location.host;
const COOKIE_NAME = 'numok_tracking';
const IMPRESSION_COOKIE_PREFIX = 'numok_impression_';
const IMPRESSION_COOKIE_HOURS = 24;
const PROGRAM_ID = '<?= $program['id'] ?>';

(function() {
    class NumokTracker {
        constructor() {
            this.init();
        }

        async init() {
            try {
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
            } catch (error) {
                console.error('Numok tracker initialization error:', error);
            }
        }

        saveTrackingData(data) {
            const expires = new Date();
            expires.setDate(expires.getDate() + <?= (int) $program['cookie_days'] ?>);
            document.cookie = `${COOKIE_NAME}=${JSON.stringify(data)};expires=${expires.toUTCString()};path=/`;
            this.trackClick(data).catch(console.error);
        }

        async trackClick(data) {
            try {
                const trackingEnabled = await this.checkTrackingEnabled();
                if (!trackingEnabled) return;

                const response = await fetch(`https://${API_DOMAIN}/api/tracking/click`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                if (!response.ok) {
                    throw new Error('Failed to track click');
                }
            } catch (error) {
                console.error('Error tracking click:', error);
            }
        }

        async trackImpression(trackingCode) {
            const impressionCookie = this.getImpressionCookieName(trackingCode);
            if (this.getCookie(impressionCookie)) {
                return;
            }

            try {
                const response = await fetch(`https://${API_DOMAIN}/api/tracking/impression`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        program_id: PROGRAM_ID,
                        tracking_code: trackingCode,
                        url: window.location.href
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to track impression');
                }

                const expires = new Date();
                expires.setTime(expires.getTime() + IMPRESSION_COOKIE_HOURS * 60 * 60 * 1000);
                document.cookie = `${impressionCookie}=1;expires=${expires.toUTCString()};path=/`;
            } catch (error) {
                console.error('Error tracking impression:', error);
            }
        }

        async checkTrackingEnabled() {
            try {
                const response = await fetch(`https://${API_DOMAIN}/api/tracking/config/${PROGRAM_ID}`);
                if (!response.ok) {
                    return false;
                }

                const config = await response.json();
                return config.track_clicks || false;
            } catch (error) {
                console.error('Error loading tracking configuration:', error);
                return false;
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
            } catch (error) {
                console.error('Error parsing tracking cookie:', error);
                return null;
            }
        }

        getImpressionCookieName(trackingCode) {
            return `${IMPRESSION_COOKIE_PREFIX}${trackingCode}`;
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
