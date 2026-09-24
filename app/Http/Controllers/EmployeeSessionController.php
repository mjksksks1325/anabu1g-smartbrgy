<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EmployeeSessionController extends Controller
{
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->route('home');
    }
}
