<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ImportHouseholdProfiling;
use App\Actions\ReadHouseholdProfiling;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseholdProfilingImportController extends Controller
{
    public function preview(Request $request, ReadHouseholdProfiling $reader, ImportHouseholdProfiling $import): JsonResponse
    {
        Gate::authorize('records.import');
        $request->validate(['file' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx'], 'purok' => ['nullable', 'string', Rule::exists('puroks', 'name')->where('is_active', true)]]);
        $raw = $reader->read($request->file('file'));
        $mapping = $import->mapping($raw['headers']);
        $token = Str::random(40);
        $request->session()->put('household_profiling_import', ['raw' => $raw, 'token' => $token, 'expires_at' => now()->addMinutes(30)->timestamp, 'owner' => $request->user()->id]);

        return response()->json(['token' => $token, 'headers' => $raw['headers'], 'mapping' => $mapping, 'fields' => $import->fields(), ...$import->preview($raw, $mapping, $request->input('purok'))]);
    }

    public function review(Request $request, ImportHouseholdProfiling $import): JsonResponse
    {
        Gate::authorize('records.import');
        [$state, $input] = $this->state($request, $import);

        return response()->json($import->preview($state['raw'], $input['mapping'], $input['purok'] ?? null, $input['rows'] ?? []));
    }

    public function store(Request $request, ImportHouseholdProfiling $import): JsonResponse
    {
        Gate::authorize('records.import');
        [$state, $input] = $this->state($request, $import);
        $result = $import->save($import->preview($state['raw'], $input['mapping'], $input['purok'] ?? null, $input['rows'] ?? []));
        $request->session()->forget('household_profiling_import');

        return response()->json(['message' => 'Household profiling imported successfully.', 'result' => $result], 201);
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    private function state(Request $request, ImportHouseholdProfiling $import): array
    {
        $request->validate(['token' => ['required', 'string', 'size:40']]);
        $state = $request->session()->get('household_profiling_import');
        if (! is_array($state) || ($state['owner'] ?? null) !== $request->user()->id || ($state['expires_at'] ?? 0) < now()->timestamp || ! hash_equals($state['token'], (string) $request->input('token'))) {
            throw ValidationException::withMessages(['file' => 'The preview expired or was already imported. Upload the file again.']);
        }
        $input = $request->validate([
            'token' => ['required', 'string'],
            'mapping' => ['required', 'array:'.implode(',', $import->fields())],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:'.(count($state['raw']['headers']) - 1)],
            'purok' => ['nullable', 'string', Rule::exists('puroks', 'name')->where('is_active', true)],
            'rows' => ['sometimes', 'array', 'max:500'],
            'rows.*' => ['array:id,fields,is_head,action,resident_id,confirm_duplicate'],
            'rows.*.id' => ['required', 'integer', 'distinct', 'min:0', 'max:'.(count($state['raw']['rows']) - 1)],
            'rows.*.fields' => ['sometimes', 'array:'.implode(',', $import->fields())],
            'rows.*.fields.*' => ['nullable', 'string', 'max:1000'],
            'rows.*.is_head' => ['sometimes', 'boolean'],
            'rows.*.action' => ['required', Rule::in(['create', 'link', 'skip', 'resolve'])],
            'rows.*.resident_id' => ['nullable', 'integer'],
            'rows.*.confirm_duplicate' => ['sometimes', 'boolean'],
        ]);

        return [$state, $input];
    }
}
