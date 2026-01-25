<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForceGuestUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always provide a dummy user instance that implements required interfaces
        $user = new class implements Authenticatable, FilamentUser
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getRememberToken()
            {
                return '';
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return '';
            }

            public function canAccessPanel(Panel $panel): bool
            {
                return true;
            }

            // Add properties that might be expected
            public $id = 1;

            public $name = 'Guest Admin';

            public $email = 'admin@example.com';
        };

        Auth::setUser($user);

        return $next($request);
    }
}
