<?php

namespace App\Providers;

use App\Auth\WordPressUserProvider;
use App\Http\Responses\LoginResponse;
use App\Models\Email;
use App\Models\PassportClient;
use App\Models\Relatie;
use App\Observers\EmailGoogleSyncObserver;
use App\Observers\RelatieGoogleSyncObserver;
use App\OpenId\OauthClientContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
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
        $this->app->scoped(OauthClientContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassport();
        $this->configureAuth();

        Relatie::observe(RelatieGoogleSyncObserver::class);
        Email::observe(EmailGoogleSyncObserver::class);
    }

    protected function configureAuth(): void
    {
        Auth::provider('wordpress-eloquent', function ($app, array $config) {
            return new WordPressUserProvider($app['hash'], $config['model']);
        });
    }

    protected function configurePassport(): void
    {
        Passport::useClientModel(PassportClient::class);

        Passport::tokensCan([
            'openid' => 'OpenID Connect',
            'profile' => __('Your name'),
            'email' => __('Your email address'),
            'roles' => __('Your role'),
        ]);

        Passport::defaultScopes(['openid']);

        Passport::authorizationView(fn (array $params) => inertia('auth/oauth-authorize', array_merge($params, [
            'csrfToken' => csrf_token(),
        ])));
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
