<?php

namespace App\Http\Middleware;

use App\Models\RelatieType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->toArray() ?? [],
                'roles' => $request->user()?->getRoleNames()->toArray() ?? [],
                'relatie_id' => $request->user()?->relatie?->id,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => app()->getLocale(),
            'translations' => fn () => $this->getTranslations(),
            'sidebarRelatieTypes' => RelatieType::all(),
        ];
    }

    private function getTranslations(): array
    {
        $locale = app()->getLocale();

        if (app()->isProduction()) {
            return Cache::rememberForever("translations.{$locale}", fn () => $this->loadTranslations($locale));
        }

        return $this->loadTranslations($locale);
    }

    private function loadTranslations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
}
