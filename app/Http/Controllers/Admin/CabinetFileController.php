<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabinetDevice;
use App\Models\CabinetFile;
use App\Models\CabinetReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CabinetFileController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('file_reference')) {
            $request->merge(['file_reference' => Str::upper(trim((string) $request->input('file_reference')))]);
        }

        $data = $request->validate([
            'file_reference' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._\/-]*\z/', Rule::unique('cabinet_files', 'file_reference')],
            'file_name' => ['required', 'string', 'max:255'],
            'cabinet_device_id' => ['required', 'integer', 'exists:cabinet_devices,id'],
            'drawer_reference' => ['required', Rule::in([CabinetFile::DRAWER_1, CabinetFile::DRAWER_2])],
            'rfid_epc' => ['prohibited'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $file = CabinetFile::query()->create([
                'file_reference' => $data['file_reference'],
                'file_name' => trim($data['file_name']),
                'cabinet_device_id' => $data['cabinet_device_id'],
                'drawer_reference' => $data['drawer_reference'],
            ]);

            $this->recordAudit($request, 'admin.cabinet-files.registered', $file->file_reference, null, $file->only(['file_name', 'cabinet_device_id', 'drawer_reference', 'rfid_epc']));
        });

        return back()->with('status', 'Folder added to the registry. RFID enrollment is waiting for Raspberry Pi integration.');
    }

    public function update(Request $request, CabinetFile $cabinetFile): RedirectResponse
    {
        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'cabinet_device_id' => ['required', 'integer', 'exists:cabinet_devices,id'],
            'drawer_reference' => ['required', Rule::in([CabinetFile::DRAWER_1, CabinetFile::DRAWER_2])],
            'rfid_epc' => ['prohibited'],
        ]);

        DB::transaction(function () use ($data, $request, $cabinetFile): void {
            $file = CabinetFile::query()->lockForUpdate()->findOrFail($cabinetFile->id);
            $before = $file->only(['file_name', 'cabinet_device_id', 'drawer_reference', 'rfid_epc']);
            $file->fill([
                'file_name' => trim($data['file_name']),
                'cabinet_device_id' => $data['cabinet_device_id'],
                'drawer_reference' => $data['drawer_reference'],
            ])->save();
            $this->recordAudit($request, 'admin.cabinet-files.updated', $file->file_reference, $before, $file->only(array_keys($before)));
        });

        return back()->with('status', 'Folder details updated.');
    }

    public function archive(Request $request, CabinetFile $cabinetFile): JsonResponse
    {
        DB::transaction(function () use ($request, $cabinetFile): void {
            $file = CabinetFile::query()->lockForUpdate()->findOrFail($cabinetFile->id);
            $before = ['is_active' => $file->is_active];
            $file->is_active = false;
            $file->save();
            $this->recordAudit($request, 'admin.cabinet-files.archived', $file->file_reference, $before, ['is_active' => false]);
        });

        return response()->json(['message' => 'Folder archived. Its movement history is retained.']);
    }

    public function restore(Request $request, CabinetFile $cabinetFile): RedirectResponse
    {
        DB::transaction(function () use ($request, $cabinetFile): void {
            $file = CabinetFile::query()->lockForUpdate()->findOrFail($cabinetFile->id);
            $before = ['is_active' => $file->is_active];
            $file->is_active = true;
            $file->save();
            $this->recordAudit($request, 'admin.cabinet-files.restored', $file->file_reference, $before, ['is_active' => true]);
        });

        return back()->with('status', 'Folder record restored.');
    }

    public function updateReaders(Request $request, CabinetDevice $cabinet): RedirectResponse
    {
        foreach (['drawer_1_reader_identifier', 'drawer_2_reader_identifier'] as $readerField) {
            if ($request->filled($readerField)) {
                $request->merge([$readerField => Str::lower(trim((string) $request->input($readerField)))]);
            }
        }

        $drawerOneReader = $cabinet->readers()->where('drawer_reference', CabinetFile::DRAWER_1)->first();
        $drawerTwoReader = $cabinet->readers()->where('drawer_reference', CabinetFile::DRAWER_2)->first();
        $data = $request->validate([
            'drawer_1_reader_identifier' => [
                'required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/',
                Rule::unique('cabinet_readers', 'reader_identifier')->ignore($drawerOneReader?->id),
            ],
            'drawer_2_reader_identifier' => [
                'required', 'string', 'max:80', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/',
                Rule::unique('cabinet_readers', 'reader_identifier')->ignore($drawerTwoReader?->id),
            ],
        ]);
        if ($data['drawer_1_reader_identifier'] === $data['drawer_2_reader_identifier']) {
            throw ValidationException::withMessages([
                'drawer_2_reader_identifier' => 'Gumamit ng magkaibang reader identifier para sa bawat drawer.',
            ]);
        }

        DB::transaction(function () use ($data, $request, $cabinet): void {
            $before = $cabinet->readers()
                ->orderBy('drawer_reference')
                ->get(['reader_identifier', 'drawer_reference'])
                ->keyBy('drawer_reference')
                ->map(fn (CabinetReader $reader): string => $reader->reader_identifier)
                ->all();

            foreach ([CabinetFile::DRAWER_1 => 'drawer_1_reader_identifier', CabinetFile::DRAWER_2 => 'drawer_2_reader_identifier'] as $drawer => $field) {
                $cabinet->readers()->updateOrCreate(
                    ['drawer_reference' => $drawer],
                    ['reader_identifier' => $data[$field], 'model' => 'YRM100', 'is_active' => true],
                );
            }

            $after = $cabinet->readers()
                ->orderBy('drawer_reference')
                ->get(['reader_identifier', 'drawer_reference'])
                ->keyBy('drawer_reference')
                ->map(fn (CabinetReader $reader): string => $reader->reader_identifier)
                ->all();

            $this->recordAudit($request, 'admin.cabinet-readers.drawer-mapping-updated', $cabinet->identifier, $before, $after);
        });

        return back()->with('status', 'YRM100 reader aliases are saved for each drawer. Hardware connection remains pending until the Raspberry Pi code is available.');
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function recordAudit(Request $request, string $action, string $record, ?array $before, ?array $after): void
    {
        DB::table('administrative_audits')->insert([
            'user_id' => $request->user()->id,
            'actor' => $request->user()->name,
            'action' => $action,
            'type' => 'cabinet',
            'record' => $record,
            'before_assignments' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_assignments' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
