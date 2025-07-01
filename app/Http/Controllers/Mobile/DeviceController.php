<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function register(Request $request)
    {
        return $this->mobileSuccess([], 'Device registered successfully');
    }

    //generate device id
    public function generateDeviceId()
    {
        return 'device-' . bin2hex(random_bytes(16));
    }

    public function index(Request $request)
    {
        return response()->json([
            'devices' => $request->user()->devices,
            'current_device_id' => $request->current_device?->device_id,
        ]);
    }

    public function revoke(Request $request, UserDevice $device)
    {
        if ($device->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Delete associated tokens
        $request->user()->tokens()
            ->where('name', $device->device_id)
            ->delete();

        $device->delete();

        return response()->json(['message' => 'Device revoked']);
    }
}
