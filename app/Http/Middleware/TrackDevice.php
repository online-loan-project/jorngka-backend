<?php

namespace App\Http\Middleware;

use App\Models\UserDevice;
use Closure;
use Illuminate\Http\Request;

class TrackDevice
{
    public function handle(Request $request, Closure $next)
    {
        // Skip if it's an authentication endpoint
        if ($request->is('auth/*')) {
            return $next($request);
        }

        // Get or create device
        $deviceId = $request->header('X-Device-ID') ?? $request->ip();
        $deviceName = $request->header('X-Device-Name') ?? 'Unknown Device';
        $deviceType = $request->header('X-Device-Type', 'web'); // Default to 'web' if not provided

        logger($deviceId);
        if ($deviceId) {
            $device = UserDevice::firstOrCreate(
                ['device_id' => $deviceId],
                [
                    'device_name' => $deviceName,
                    'device_type' => $deviceType,
                    'ip_address' => $request->ip(),
                    'os' => $request->header('User-Agent'),
                    'last_active_at' => now(),
                ]
            );

            // Update last active time for existing devices
            if ($device->wasRecentlyCreated === false) {
                $device->update(['last_active_at' => now()]);
            }

            // Associate with user if authenticated
            if ($request->user()) {
                $device->update(['user_id' => $request->user()->id]);
            }

            // Add device to request for controller access
            $request->merge(['current_device' => $device]);
        }

        return $next($request);
    }
}