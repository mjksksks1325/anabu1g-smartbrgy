<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResidentLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ResidentSessionController extends Controller
{
    public function store(ResidentLoginRequest $request): RedirectResponse
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$request->validated('email')])
            ->first();

        if (! $user?->canUseResidentPortal() || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided resident account credentials are incorrect or the account cannot currently use online services.',
            ]);
        }

        Auth::guard('resident')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $destination = $request->session()->pull('portal_login_destination');
        $service = $request->session()->pull('portal_service');
        if ($destination === 'request' || $service !== null) {
            return redirect()->route('portal.request.create', ['service' => $service]);
        }

        return redirect()->route('portal.account');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('resident')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
