<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

trait Security
{
    /**
     * Get geolocation data for an IP address (excluding private/reserved ranges)
     *
     * @param string $ip
     * @return array|null
     * @throws \Exception if geoip service fails in debug mode
     */
    public function getGeoData(string $ip): ?array
    {
        if (!$this->isValidPublicIp($ip)) {
            return null;
        }

        try {
            $location = $this->geoip()->getLocation($ip);
            return $this->filterGeoData($location->toArray());
        } catch (Exception $e) {
            Log::warning("GeoIP lookup failed for {$ip}: {$e->getMessage()}");

            if (config('app.debug')) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * Sanitize HTTP headers for safe storage
     *
     * @param array $headers
     * @return array
     */
    public function sanitizeHeaders(array $headers): array
    {
        return collect($headers)
            ->map(function ($header) {
                if (is_array($header)) {
                    return json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                return mb_substr((string)$header, 0, 255);
            })
            ->toArray();
    }

    /**
     * Get comprehensive request metadata for security/audit purposes
     *
     * @param Request $request
     * @return array
     */
    public function getRequestMetadata(Request $request): array
    {
        return [
            'ip_address' => $request->getClientIp(),
            'user_agent' => mb_substr($request->userAgent() ?? '', 0, 255),
            'forwarded_ip' => $request->header('X-Forwarded-For'),
            'referer' => $request->header('referer'),
            'host' => $request->getHost(),
            'is_secure' => $request->secure(),
            'timestamp' => now()->toIso8601String(),
            'geo_data' => $this->getGeoData($request->ip()),
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query(),
            'body' => json_encode(
                collect($request->all())
                    ->except(['password', 'password_confirmation'])
                    ->toArray(),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'route' => $request->route() ? [
                'name' => $request->route()->getName(),
                'action' => $request->route()->getActionName(),
            ] : null,
            'user_id' => $request->user() ? $request->user()->id : null,
        ];
    }

    /**
     * Validate IP is public and not in private/reserved ranges
     */
    protected function isValidPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Filter sensitive fields from geo data
     */
    protected function filterGeoData(array $geoData): array
    {
        return array_intersect_key($geoData, array_flip([
            'ip',
            'country',
            'city',
            'state',
            'state_code',
            'postal_code',
            'timezone',
            'continent',
        ]));
    }

    /**
     * Get geoip service instance with null object pattern fallback
     */
    protected function geoip()
    {
        try {
            return app('geoip');
        } catch (Exception $e) {
            return new class {
                public function getLocation($ip) {
                    return new class {
                        public function toArray() {
                            return [];
                        }
                    };
                }
            };
        }
    }
}