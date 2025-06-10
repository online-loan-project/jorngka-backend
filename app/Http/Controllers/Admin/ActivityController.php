<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * Display a listing of the activities.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Fetch activities from the database with optional filters
        $query = Activity::with(['causer', 'subject'])->latest();

        // Apply search filter if provided
        if ($request->has('search')) {
            $search = $request->input('search');
            logger($search);
            $query->where(function($q) use ($search) {
                $q->where('log_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('causer', function($q) use ($search) {
                        $q->where('id', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Apply log name filter if provided
        if ($request->has('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        $activities = $query->paginate($request->input('per_page', 10));

        return $this->success($activities, 'Activities', 'Activities retrieved successfully.');
    }

    /**
     * Display the specified activity.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $activity = Activity::with(['causer', 'subject'])->findOrFail($id);

        return $this->success($activity, 'Activity Details', 'Activity retrieved successfully.');
    }

    /**
     * Get distinct log names for filtering.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logNames()
    {
        $logNames = Activity::select('log_name')
            ->distinct()
            ->pluck('log_name')
            ->sort()
            ->values();

        if ($logNames->isEmpty()) {
            return $this->failed(null, 'No Log Names Found', 'No log names available.', 404);
        }

        return $this->success($logNames, 'Log Names', 'Log names retrieved successfully.');
    }

    /**
     * Remove the specified activity from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $activity = Activity::findOrFail($id);
        $activity->delete();

        return $this->success(null, 'Activity Deleted', 'Activity deleted successfully.', 204);
    }
}