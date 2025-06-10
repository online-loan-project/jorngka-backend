<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Borrower;
use App\Models\Credit;
use App\Models\CreditScore;
use App\Models\CreditTransaction;
use App\Models\IncomeInformation;
use App\Models\InterestRate;
use App\Models\Liveliness;
use App\Models\Loan;
use App\Models\NidInformation;
use App\Models\PhoneOtp;
use App\Models\RequestLoan;
use App\Models\ScheduleRepayment;
use App\Models\User;
use App\Observers\ModelActivityObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerObserversForAllModels();
    }

    protected function registerObserversForAllModels(): void
    {
        $models = collect(scandir(app_path('Models')))
            ->filter(fn ($file) => str_ends_with($file, '.php'))
            ->map(fn ($file) => 'App\\Models\\' . str_replace('.php', '', $file))
            ->filter(fn ($class) => is_subclass_of($class, Model::class));

        foreach ($models as $model) {
            $model::observe(\App\Observers\ModelActivityObserver::class);
        }
    }
}
