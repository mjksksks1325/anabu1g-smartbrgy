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
        abort_unless(in_array($request->user()->role, ['admin', 'staff'], true), 403);

        $events = DB::table('administrative_audits')->latest('id')->limit(500)->get()
            ->map(fn (object $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'action' => str_replace(['admin.', '-', '.'], ['', ' ', ' / '], $event->action),
                'detail' => $event->record ? 'Record ID: '.$event->record : 'Administrative operation',
                'user' => $event->actor,
                'date' => Carbon::parse($event->created_at)->toDateString(),
                'time' => Carbon::parse($event->created_at)->format('M d, Y H:i'),
                'icon' => '•',
                'severity' => 'ok',
            ]);

        return response()->json(['events' => $events]);
    }
}
