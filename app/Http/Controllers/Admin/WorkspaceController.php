<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\Incident;
use App\Models\ResidentRequestRestriction;
use App\StaffPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    /** @var array<string, string> */
    public const SCREEN_ROUTES = [
        'dashboard' => 'staff.dashboard',
        'demographics' => 'staff.demographics',
        'records' => 'staff.residents.index',
        'voters' => 'staff.voters',
        'certificates' => 'staff.document-requests.index',
        'request-records' => 'staff.request-eligibility',
        'incidents' => 'staff.incidents.index',
        'audit' => 'admin.audit',
        'users' => 'admin.users.index',
        'settings' => 'admin.settings',
    ];

    public function __invoke(Request $request): View|JsonResponse|RedirectResponse
    {
        $screen = $request->route('screen', 'dashboard');
        if ($request->expectsJson()) {
            $dataResponse = match ($screen) {
                'records' => $request->routeIs('staff.households.page') ? app(HouseholdController::class)->index($request) : app(ResidentController::class)->index($request),
                'incidents' => app(IncidentController::class)->index($request),
                'users' => app(UserController::class)->index($request),
                'certificates' => app(DocumentRequestController::class)->index(),
                default => null,
            };
            if ($dataResponse !== null) {
                return $dataResponse;
            }
        }

        if ($request->routeIs('staff.dashboard', 'admin.dashboard') && $request->query->has('screen')) {
            $legacyScreen = $request->query('screen');
            $screen = is_string($legacyScreen) && isset(self::SCREEN_ROUTES[$legacyScreen]) ? $legacyScreen : 'dashboard';
            $this->authorizeScreen($screen);
            $query = $request->query();
            unset($query['screen']);

            return redirect()->route($screen === 'dashboard' && $request->user()->role === 'admin' ? 'admin.dashboard' : self::SCREEN_ROUTES[$screen], $query);
        }

        if ($request->routeIs('admin.dashboard') && ! $request->user()->isSuperAdmin()) {
            abort_if($request->expectsJson(), 403);

            return redirect()->route(StaffPermissions::landing($request->user()), $request->query());
        }
        if ($screen === 'incidents' && $request->boolean('submitted')) {
            Gate::authorize('create', Incident::class);

            return app(StaffSubmissionController::class)->confirmation($request);
        }
        if ($screen === 'incidents' && ! $request->user()->hasAnyPermission(['incidents.view', 'vawc.view'])) {
            Gate::authorize('create', Incident::class);

            return app(StaffSubmissionController::class)->create($request);
        }
        $this->authorizeScreen($screen);
        $screenRoutes = self::SCREEN_ROUTES;
        if (! $request->user()->hasPermission('records.view') && $request->user()->hasPermission('households.view')) {
            $screenRoutes['records'] = 'staff.households.page';
        }
        $screenRoutes['dashboard'] = $request->user()->role === 'admin' ? 'admin.dashboard' : 'staff.dashboard';

        return view('admin.dashboard', [
            'requests' => $request->user()->hasPermission('documents.view') ? DocumentRequest::latest()->get() : collect(),
            'activeScreen' => $screen,
            'screenRoutes' => array_map(fn (string $name): string => route($name), $screenRoutes),
        ]);
    }

    private function authorizeScreen(string $screen): void
    {
        $permissions = ['dashboard' => ['dashboard.view'], 'demographics' => ['demographics.view'], 'records' => request()->routeIs('staff.households.page') ? ['households.view'] : ['records.view'], 'voters' => ['voters.view'], 'certificates' => ['documents.view'], 'request-records' => ['eligibility.view', 'documents.view']];
        if (isset($permissions[$screen])) {
            abort_unless(request()->user()->hasAnyPermission($permissions[$screen]), 403);
        }
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
