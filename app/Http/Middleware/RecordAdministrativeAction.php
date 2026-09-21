<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordAdministrativeAction
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        return DB::transaction(function () use ($request, $next): Response {
            $response = $next($request);

            if ($response->getStatusCode() < 400 && ! $request->session()->has('errors')) {
                $name = $request->route()->getName();
                $parameters = collect($request->route()->parameters())->map(
                    fn (mixed $value): string => (string) ($value instanceof Model ? $value->getKey() : $value),
                )->implode(', ');
                $type = match (true) {
                    str_contains($name, 'users') => 'security',
                    str_contains($name, 'certificate'), str_contains($name, 'document-request') => 'cert',
                    str_contains($name, 'incident') => 'incident',
                    default => 'record',
                };

                DB::table('administrative_audits')->insert([
                    'user_id' => $request->user()->id,
                    'actor' => $request->user()->name,
                    'action' => $name,
                    'type' => $type,
                    'record' => $parameters ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $response;
        });
    }
}
