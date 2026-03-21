<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassport();
    }

    protected function configurePassport(): void
    {
        Passport::tokensCan([
            'openid' => 'Enable OpenID Connect',
            'profile' => 'Access user profile (name)',
            'email' => 'Access user email address',
            'roles' => 'Access user roles',
        ]);

        Passport::defaultScopes(['openid']);

        Passport::authorizationView(fn (array $params) => inertia('auth/oauth-authorize', $params));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
