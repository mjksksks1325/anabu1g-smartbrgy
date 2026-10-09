<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $events = DB::table('administrative_audits')
            ->where(fn ($query) => $query->where('action', '!=', 'auth.login')->orWhere('record', '!=', 'resident_portal')->orWhereNull('record'))
            ->latest('id')->limit(500)->get()
            ->map(fn (object $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'action' => $event->type === 'cabinet'
                    ? match ($event->access_result) {
                        'attempted' => 'Cabinet access attempted',
                        'denied' => 'Cabinet access denied',
                        'granted' => 'Cabinet access granted',
                        'opened' => 'Cabinet door opened (sensor confirmed)',
                        default => 'Cabinet access event',
                    }
                    : ($event->action === 'auth.login' ? 'Login' : str_replace(['admin.', '-', '.'], ['', ' ', ' / '], $event->action)),
                'detail' => $event->action === 'admin.users.permissions-changed'
                    ? 'Staff #'.$event->target_user_id.' - Before: '.$event->before_assignments.'; After: '.$event->after_assignments
                    : ($event->type === 'cabinet'
                    ? implode(' · ', array_filter([
                        'Cabinet: '.$event->cabinet_identifier,
                        $event->user_id ? 'Employee ID: '.$event->user_id : null,
                        $event->rpi_employee_id ? 'RPi ID: '.$event->rpi_employee_id : null,
                        $event->access_result === 'granted' ? 'Opening not confirmed' : null,
                        $event->access_result === 'opened' ? 'Door sensor reported open' : null,
                        $event->authentication_method ? 'Device-reported verified authentication: '.str_replace('_', ' + ', strtoupper($event->authentication_method)) : null,
                    ]))
                    : ($event->action === 'auth.login'
                        ? 'Successful sign in'
                        : ($event->action === 'portal.account.registered' ? 'Resident portal account created' : ($event->record ? 'Record ID: '.$event->record : 'Administrative operation')))),
                'user' => $event->actor,
                'actor_id' => $event->user_id,
                'target_staff_id' => $event->target_user_id,
                'before_assignments' => $event->before_assignments ? json_decode($event->before_assignments, true, 512, JSON_THROW_ON_ERROR) : null,
                'after_assignments' => $event->after_assignments ? json_decode($event->after_assignments, true, 512, JSON_THROW_ON_ERROR) : null,
                'date' => ($event->type === 'cabinet' ? Carbon::parse($event->created_at, 'UTC')->setTimezone(config('app.timezone')) : Carbon::parse($event->created_at))->toDateString(),
                'time' => ($event->type === 'cabinet' ? Carbon::parse($event->created_at, 'UTC')->setTimezone(config('app.timezone')) : Carbon::parse($event->created_at))->format('M d, Y H:i'),
                'icon' => '•',
                'severity' => 'ok',
            ]);

        return response()->json(['events' => $events]);
    }
}
