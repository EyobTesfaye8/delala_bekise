<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('send_otp', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return [
                Limit::perHour(10)->by($request->ip()),
                Limit::perHour(3)->by($request->input('phone')),
                Limit::perMinute(1)->by($request->input('phone')), // 60-second cooldown per NFR-SEC-1
            ];
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            // For now, redirect to backend on port 8000. Will be frontend URL later.
            return url("/api/v1/auth/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}");
        });

        if ($this->app->environment('local', 'development', 'dev')) {
            $devEmail = config('mail.dev_to', 'honore8a@gmail.com');
            if (! empty($devEmail)) {
                Mail::alwaysTo($devEmail);
            }
        }
    }
}
