<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ModelActivityObserver
{
    protected array $sensitiveFields = [
        'password',
        'remember_token',
        'otp',
        'pin',
        'security_question',
        'security_answer'
    ];

    public function created(Model $model)
    {
        $this->logActivity($model, 'created');
    }

    public function updated(Model $model)
    {
        $this->logActivity($model, 'updated', [
            'old' => $this->filterSensitiveData($model->getOriginal()),
            'new' => $this->filterSensitiveData($model->getChanges())
        ]);
    }

    public function deleted(Model $model)
    {
        $this->logActivity($model, 'deleted');
    }

    public function restored(Model $model)
    {
        $this->logActivity($model, 'restored');
    }

    public function forceDeleted(Model $model)
    {
        $this->logActivity($model, 'force_deleted');
    }

    protected function logActivity(Model $model, string $event, array $properties = [])
    {
        $properties = array_merge($properties, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);

        activity()
            ->useLog(class_basename($model))
            ->on($model)
            ->by(Auth::user())
            ->withProperties($properties)
            ->event($event)
            ->log(class_basename($model) . " has been {$event}");
    }

    protected function filterSensitiveData(array $data): array
    {
        return collect($data)
            ->reject(fn ($value, $key) => in_array($key, $this->sensitiveFields))
            ->toArray();
    }
}