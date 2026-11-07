<?php

use App\Exceptions\MailDeliveryUnavailableException;
use App\Http\Controllers\ResidentEmailVerificationController;
use App\Http\Middleware\ApplyPortalLocale;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsurePersonnelTwoFactorAccount;
use App\Http\Middleware\PreventAccountCaching;
use App\Http\Middleware\RequirePrivilegedTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Responses\PasswordResetLinkRequestResponse;
use App\MailDelivery;
use App\PortalLocalization;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $middleware->web(prepend: [PreventAccountCaching::class], append: [ApplyPortalLocale::class, EnsureActiveAccount::class, EnsurePersonnelTwoFactorAccount::class, RequirePrivilegedTwoFactor::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ApplyPortalLocale::class);
        // Only proxies listed in TRUSTED_PROXIES (config/trustedproxy.php) are believed; by default none are. From
        // them, only the client address, port and scheme are taken, never the host.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request): JsonResponse|RedirectResponse|null {
            if ($request->routeIs('portal.request.store')) {
                $seconds = max(1, (int) ($exception->getHeaders()['Retry-After'] ?? 60));

                return response()->json(['message' => __('You have sent several requests in a short time. Please wait :seconds seconds, then submit again. Nothing was lost: your request was not sent yet.', ['seconds' => $seconds])], 429, $exception->getHeaders());
            }
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
        // A form larger than PHP accepts is refused before the web middleware runs (cookies still encrypted, no language
        // set): answer in the visitor's language, as JSON for background uploads, and never with server details.
        $exceptions->render(function (PostTooLargeException $exception, Request $request): Response {
            app()->setLocale(PortalLocalization::localeFromEncryptedCookie($request));
            if ($request->expectsJson()) {
                return response()->json(['message' => __('The file you sent is too large, so nothing was saved. Choose a smaller file (resident photos can be up to 10 MB) and try again.')], 413);
            }
            $referer = (string) $request->headers->get('referer');
            $backUrl = parse_url($referer, PHP_URL_HOST) === $request->getHost() ? $referer : null;

            return response()->view('errors.413', ['backUrl' => $backUrl], 413);
        });
        // An expired or altered email confirmation link explains itself instead of showing a bare 403 page.
        $exceptions->render(function (InvalidSignatureException $exception, Request $request): ?RedirectResponse {
            return $request->routeIs('portal.verification.verify') ? ResidentEmailVerificationController::rejectedLink() : null;
        });
        // A reset email that was not sent (mail server failure, or a server that does not deliver email) is reported
        // and recorded in the audit log for administrators, but answered like any other reset request, so the reply
        // never differs between addresses that have accounts and addresses that do not.
        $exceptions->render(function (TransportExceptionInterface|MailDeliveryUnavailableException $exception, Request $request): ?Response {
            // A staff member asking for a new verification link is told it was not sent, never that it was.
            if ($request->routeIs('verification.send')) {
                if ($exception instanceof TransportExceptionInterface) {
                    report($exception);
                }

                return back()->withErrors(['email' => __('The verification email was not sent: this server does not send email yet, or the mail server refused it. Ask the system administrator to check the email settings.')]);
            }
            if (! $request->routeIs('password.email')) {
                return null;
            }
            MailDelivery::recordResetEmailFailure($request, $exception);

            return (new PasswordResetLinkRequestResponse)->toResponse($request);
        });
        // Database errors never reach the screen, even with APP_DEBUG on: their messages carry SQL, bound values (resident
        // details) and the database file path. People see a generic message with a short reference; the full error is
        // written to the protected application log under that reference.
        $databaseError = function (Throwable $exception, Request $request): Response {
            $reference = Str::upper(Str::random(8));
            Log::error('Database error (reference '.$reference.')', ['exception' => $exception, 'path' => $request->path(), 'user_id' => $request->user()?->getAuthIdentifier()]);
            $message = __('Something went wrong on our side and the action was not completed. Try again; if it keeps happening, give the system administrator this reference: :reference.', ['reference' => $reference]);

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => $message, 'reference' => $reference], 500)
                : response()->view('errors.database', ['reference' => $reference], 500);
        };
        $exceptions->render(fn (QueryException $exception, Request $request): Response => $databaseError($exception, $request));
        $exceptions->render(fn (PDOException $exception, Request $request): Response => $databaseError($exception, $request));
        $exceptions->dontFlash(['activation_code', 'resident_number']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
