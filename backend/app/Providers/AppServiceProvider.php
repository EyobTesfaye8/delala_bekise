<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

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
            return [
                Limit::perHour(10)->by($request->ip()),
                Limit::perHour(3)->by($request->input('phone')),
            ];
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            // For now, redirect to backend on port 8000. Will be frontend URL later.
            return url("/api/v1/auth/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}");
        });
    }
}
