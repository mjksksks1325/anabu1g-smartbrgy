<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResidentLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
                'email' => __('The provided resident account credentials are incorrect or the account cannot currently use online services.'),
            ]);
        }

        Auth::guard('resident')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $destination = $request->session()->pull('portal_login_destination');
        $service = $request->session()->pull('portal_service');
        if ($destination === 'request' || $service !== null) {
            return redirect()->route('portal.request.create', ['service' => $service]);
        }

        return redirect()->to($this->residentDestination($request));
    }

    /**
     * Return to the resident page that asked for sign-in, otherwise open the dashboard.
     * Only resident portal pages are honoured; personnel URLs stay in the session for staff sign-in.
     */
    private function residentDestination(Request $request): string
    {
        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && $this->isResidentPortalPage($intended)) {
            $request->session()->forget('url.intended');

            return $intended;
        }

        return route('portal.dashboard');
    }

    private function isResidentPortalPage(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return str_starts_with($url, url('/portal').'/')
            && ! Str::startsWith($path, ['/portal/login', '/portal/register', '/portal/logout', '/portal/locale', '/portal/csrf-token', '/portal/translations.js']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('resident')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
