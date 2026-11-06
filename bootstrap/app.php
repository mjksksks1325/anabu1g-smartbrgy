<?php

use App\Http\Controllers\ResidentEmailVerificationController;
use App\Http\Middleware\ApplyPortalLocale;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsurePersonnelTwoFactorAccount;
use App\Http\Middleware\PreventAccountCaching;
use App\Http\Responses\PasswordResetLinkRequestResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [PreventAccountCaching::class], append: [ApplyPortalLocale::class, EnsureActiveAccount::class, EnsurePersonnelTwoFactorAccount::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ApplyPortalLocale::class);
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request): JsonResponse|RedirectResponse|null {
            if ($request->is('portal', 'portal/*') && $request->expectsJson()) {
                return response()->json(['message' => __('Too many attempts. Please wait before trying again.')], 429, $exception->getHeaders());
            }
            if ($request->routeIs('portal.verification.verify', 'portal.verification.send')) {
                return ResidentEmailVerificationController::rejectedLink(__('Too many attempts. Please wait before trying again.'));
            }

            return null;
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request): ?JsonResponse {
            if ($request->is('portal', 'portal/*') && $request->expectsJson()) {
                return response()->json(['message' => __('Sign in to your resident account to continue.')], 401);
            }

            return null;
        });
        // An expired or altered email confirmation link explains itself instead of showing a bare 403 page.
        $exceptions->render(function (InvalidSignatureException $exception, Request $request): ?RedirectResponse {
            return $request->routeIs('portal.verification.verify') ? ResidentEmailVerificationController::rejectedLink() : null;
        });
        // A failed reset email is reported but answered like any other reset request, so the reply never
        // differs between addresses that have accounts and addresses that do not.
        $exceptions->render(function (TransportExceptionInterface $exception, Request $request): ?Response {
            return $request->routeIs('password.email') ? (new PasswordResetLinkRequestResponse)->toResponse($request) : null;
        });
        $exceptions->dontFlash(['activation_code', 'resident_number']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
