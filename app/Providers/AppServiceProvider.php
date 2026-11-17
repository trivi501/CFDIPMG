<?php

namespace App\Providers;

use App\Models\User;
use App\Support\RegistroAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureLogViewer();

        RegistroAuditoria::registrar();
    }

    /**
     * Visor de logs (/log-viewer, paquete opcodesio/log-viewer): solo el rol Admin, por rol y no por
     * permiso para que no se pueda otorgar desde la pantalla de Roles (igual que la bitácora).
     * Los logs se pueden leer y descargar, nunca borrar desde la pantalla.
     */
    protected function configureLogViewer(): void
    {
        Gate::define('viewLogViewer', fn (?User $user) => $user?->hasRole('Admin') ?? false);
        Gate::define('deleteLogFile', fn (?User $user, mixed $archivo = null) => false);
        Gate::define('deleteLogFolder', fn (?User $user, mixed $carpeta = null) => false);
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
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
