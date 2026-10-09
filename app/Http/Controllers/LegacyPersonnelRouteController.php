<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\ResidentRequestRestriction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LegacyPersonnelRouteController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $parameters = $request->route()->parameters();
        $destination = $parameters['destination'];
        unset($parameters['destination']);
        if (str_starts_with($destination, 'staff.request-restrictions.') && ! $request->isMethodSafe()) {
            Gate::authorize('create', ResidentRequestRestriction::class);
        }
        if ($destination === 'staff.incidents.destroy') {
            Gate::authorize('delete', Incident::query()->whereKey($parameters['incident'])->firstOrFail());
        }
        $url = route($destination, $parameters);
        $query = $request->getQueryString();

        return redirect($url.($query === null ? '' : '?'.$query), $request->isMethodSafe() ? 302 : 307);
    }
}
