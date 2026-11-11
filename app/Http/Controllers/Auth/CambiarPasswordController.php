<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\RequisitosPassword;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CambiarPasswordController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/cambiar-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'requisitos' => RequisitosPassword::actuales(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => [
                'required',
                'string',
                Password::defaults(),
                'confirmed',
                function (string $attribute, mixed $value, Closure $fail) use ($request) {
                    if (Hash::check($value, $request->user()->password)) {
                        $fail('La nueva contraseña debe ser distinta a la actual.');
                    }
                },
            ],
        ]);

        $request->user()->update([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contraseña actualizada.']);

        return to_route('dashboard');
    }
}
