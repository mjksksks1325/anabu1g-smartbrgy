<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CabinetDevice;
use App\Models\CabinetFile;
use App\Models\CabinetFileEnrollmentRequest;
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
            CabinetDevice::query()->whereIn('id', [$cabinetFile->cabinet_device_id, $data['cabinet_device_id']])->orderBy('id')->lockForUpdate()->get();
            $file = CabinetFile::query()->lockForUpdate()->findOrFail($cabinetFile->id);
            if ($file->cabinet_device_id !== $cabinetFile->cabinet_device_id || $file->isRfidEnrollmentPending()) {
                throw ValidationException::withMessages(['rfid_enrollment' => 'Cancel the pending enrollment and reload the folder before editing its details.']);
            }
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

    public function requestRfidEnrollment(Request $request, CabinetFile $cabinetFile): RedirectResponse
    {
        return $this->queueRfidEnrollment($request, $cabinetFile, false);
    }

    public function replaceRfidTag(Request $request, CabinetFile $cabinetFile): RedirectResponse
    {
        return $this->queueRfidEnrollment($request, $cabinetFile, true);
    }

    private function queueRfidEnrollment(Request $request, CabinetFile $cabinetFile, bool $replace): RedirectResponse
    {
        abort_unless($request->user()->hasAdministrativeAccess(), 403);

        DB::transaction(function () use ($request, $cabinetFile, $replace): void {
            CabinetDevice::query()->whereKey($cabinetFile->cabinet_device_id)->lockForUpdate()->firstOrFail();
            $file = CabinetFile::query()->lockForUpdate()->findOrFail($cabinetFile->id);
            if ($file->cabinet_device_id !== $cabinetFile->cabinet_device_id || ! $file->is_active || $file->isRfidEnrolled() !== $replace) {
                throw ValidationException::withMessages(['rfid_enrollment' => 'Use Enroll RFID tag for an active unregistered folder, or Replace tag for an enrolled folder.']);
            }
            CabinetFileEnrollmentRequest::expireForCabinet($file->cabinet_device_id);
            if (CabinetFileEnrollmentRequest::query()->where('cabinet_device_id', $file->cabinet_device_id)->where('status', CabinetFileEnrollmentRequest::PENDING)->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['rfid_enrollment' => 'This cabinet already has a pending enrollment. Finish or cancel it before enrolling another folder.']);
            }

            $reader = CabinetReader::query()
                ->where('cabinet_device_id', $file->cabinet_device_id)
                ->where('drawer_reference', $file->drawer_reference)
                ->where('is_active', true)
                ->first();

            if ($reader === null) {
                throw ValidationException::withMessages([
                    'rfid_enrollment' => 'Save an active YRM100 reader mapping for this drawer first.',
                ]);
            }

            $enrollment = CabinetFileEnrollmentRequest::query()->create([
                'request_id' => (string) Str::uuid(),
                'cabinet_file_id' => $file->id,
                'cabinet_device_id' => $file->cabinet_device_id,
                'cabinet_reader_id' => $reader->id,
                'reader_identifier' => $reader->reader_identifier,
                'folder_snapshot' => ['file_reference' => Str::upper($file->file_reference), 'file_name' => $file->file_name, 'drawer_reference' => $file->drawer_reference],
                'status' => CabinetFileEnrollmentRequest::PENDING,
                'requested_by' => $request->user()->id,
                'expires_at' => now()->addMinutes(10),
            ]);
            $this->recordAudit($request, 'admin.cabinet-files.enrollment-requested', $file->file_reference, null, [
                'request_id' => $enrollment->request_id, 'drawer_reference' => $file->drawer_reference, 'replace_tag' => $replace,
            ]);
        });

        return back()->with('status', 'RFID enrollment queued for 10 minutes. Place ONLY this folder tag inside its assigned drawer.');
    }

    public function cancelRfidEnrollment(Request $request, CabinetFile $cabinetFile): RedirectResponse
    {
        $data = $request->validate(['request_id' => ['required', 'uuid']]);
        DB::transaction(function () use ($request, $cabinetFile, $data): void {
            CabinetDevice::query()->whereKey($cabinetFile->cabinet_device_id)->lockForUpdate()->firstOrFail();
            $enrollment = CabinetFileEnrollmentRequest::query()->where('cabinet_file_id', $cabinetFile->id)
                ->where('cabinet_device_id', $cabinetFile->cabinet_device_id)->where('request_id', $data['request_id'])->lockForUpdate()->firstOrFail();
            if ($enrollment->status === CabinetFileEnrollmentRequest::ENROLLED) {
                throw ValidationException::withMessages(['rfid_enrollment' => 'An enrolled request cannot be cancelled. Use Replace tag to change its tag.']);
            }
            if ($enrollment->status === CabinetFileEnrollmentRequest::PENDING) {
                $enrollment->status = $enrollment->isPending() ? CabinetFileEnrollmentRequest::CANCELLED : CabinetFileEnrollmentRequest::EXPIRED;
                $enrollment->save();
                $this->recordAudit($request, 'admin.cabinet-files.enrollment-cancelled', $cabinetFile->file_reference, null, ['request_id' => $enrollment->request_id, 'status' => $enrollment->status]);
            }
        });

        return back()->with('status', 'RFID enrollment is no longer pending. The current tag was retained.');
    }

    public function archive(Request $request, CabinetFile $cabinetFile): JsonResponse
    {
        DB::transaction(function () use ($request, $cabinetFile): void {
            CabinetDevice::query()->whereKey($cabinetFile->cabinet_device_id)->lockForUpdate()->firstOrFail();
            $file = CabinetFile::query()->lockForUpdate()->findOrFail($cabinetFile->id);
            abort_if($file->cabinet_device_id !== $cabinetFile->cabinet_device_id, 409, 'The folder changed. Reload it before archiving.');
            $before = ['is_active' => $file->is_active];
            $file->is_active = false;
            if ($file->isRfidEnrollmentPending()) {
                $file->latestEnrollmentRequest->update(['status' => CabinetFileEnrollmentRequest::CANCELLED]);
                $this->recordAudit($request, 'admin.cabinet-files.enrollment-cancelled', $file->file_reference, null, ['request_id' => $file->latestEnrollmentRequest->request_id]);
            }
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
            CabinetDevice::query()->whereKey($cabinet->id)->lockForUpdate()->firstOrFail();
            if (CabinetFileEnrollmentRequest::query()->where('cabinet_device_id', $cabinet->id)->where('status', CabinetFileEnrollmentRequest::PENDING)->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['rfid_enrollment' => 'Finish or cancel the pending enrollment before changing reader mapping.']);
            }
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

        return back()->with('status', 'YRM100 reader aliases are saved for each drawer. Connection status will update after the Raspberry Pi reports both readers.');
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
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
