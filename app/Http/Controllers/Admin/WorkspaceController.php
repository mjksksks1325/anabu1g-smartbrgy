<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\Incident;
use App\Models\ResidentRequestRestriction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    /** @var array<string, string> */
    public const SCREEN_ROUTES = [
        'dashboard' => 'admin.dashboard',
        'demographics' => 'admin.demographics',
        'records' => 'admin.residents.index',
        'voters' => 'admin.voters',
        'certificates' => 'admin.document-requests.index',
        'request-records' => 'admin.request-eligibility',
        'incidents' => 'admin.incidents.index',
        'audit' => 'admin.audit',
        'users' => 'admin.users.index',
        'settings' => 'admin.settings',
    ];

    public function __invoke(Request $request): View|JsonResponse|RedirectResponse
    {
        $screen = $request->route('screen', 'dashboard');
        if ($request->expectsJson()) {
            $dataResponse = match ($screen) {
                'records' => app(ResidentController::class)->index($request),
                'incidents' => app(IncidentController::class)->index($request),
                'users' => app(UserController::class)->index($request),
                'certificates' => app(DocumentRequestController::class)->index(),
                default => null,
            };
            if ($dataResponse !== null) {
                return $dataResponse;
            }
        }

        Gate::authorize('viewAny', DocumentRequest::class);
        if ($request->routeIs('admin.dashboard') && $request->query->has('screen')) {
            $legacyScreen = $request->query('screen');
            $screen = is_string($legacyScreen) && isset(self::SCREEN_ROUTES[$legacyScreen]) ? $legacyScreen : 'dashboard';
            $this->authorizeScreen($screen);
            $query = $request->query();
            unset($query['screen']);

            return redirect()->route(self::SCREEN_ROUTES[$screen], $query);
        }

        $this->authorizeScreen($screen);

        return view('admin.dashboard', [
            'requests' => DocumentRequest::latest()->get(),
            'activeScreen' => $screen,
            'screenRoutes' => array_map(fn (string $name): string => route($name), self::SCREEN_ROUTES),
        ]);
    }

    private function authorizeScreen(string $screen): void
    {
        if ($screen === 'incidents') {
            Gate::authorize('viewAny', Incident::class);
        }
        if ($screen === 'request-records') {
            Gate::authorize('viewAny', ResidentRequestRestriction::class);
        }
        if (in_array($screen, ['audit', 'users', 'settings'], true)) {
            Gate::authorize('view-administration');
        }
    }
}
