<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CabinetDevice;
use App\Models\CabinetFile;
use App\Models\CabinetFileEnrollmentRequest;
use App\Models\CabinetReader;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CabinetFileEnrollmentController extends Controller
{
    public function registry(CabinetDevice $cabinet): JsonResponse
    {
        $folders = CabinetFile::query()->where('cabinet_device_id', $cabinet->id)->where('is_active', true)
            ->whereNotNull('rfid_epc')->orderBy('file_reference')
            ->get(['file_reference', 'file_name', 'drawer_reference', 'rfid_epc'])
            ->map(fn (CabinetFile $file): array => [
                'file_reference' => Str::upper($file->file_reference), 'file_name' => $file->file_name,
                'drawer_reference' => $file->drawer_reference, 'rfid_epc' => Str::upper($file->rfid_epc),
            ])
            ->filter(fn (array $folder): bool => preg_match('/\A(?:[A-F0-9]{2}){1,31}\z/', $folder['rfid_epc']) === 1)
            ->unique('file_reference')->unique('rfid_epc')->values();

        return response()->json(['folders' => $folders]);
    }

    public function pending(CabinetDevice $cabinet): JsonResponse
    {
        $enrollment = DB::transaction(function () use ($cabinet): ?CabinetFileEnrollmentRequest {
            CabinetDevice::query()->whereKey($cabinet->id)->lockForUpdate()->firstOrFail();
            CabinetFileEnrollmentRequest::expireForCabinet($cabinet->id);

            return CabinetFileEnrollmentRequest::query()->with(['cabinetFile', 'cabinetReader'])
                ->where('cabinet_device_id', $cabinet->id)->where('status', CabinetFileEnrollmentRequest::PENDING)
                ->where('expires_at', '>', now())->oldest('id')->first();
        });
        if ($enrollment === null || ! $enrollment->cabinetFile->is_active || ! $enrollment->cabinetReader->is_active
            || $enrollment->cabinetFile->cabinet_device_id !== $cabinet->id
            || $enrollment->cabinetReader->cabinet_device_id !== $cabinet->id
            || $enrollment->cabinetFile->drawer_reference !== $enrollment->folder_snapshot['drawer_reference']
            || $enrollment->cabinetReader->drawer_reference !== $enrollment->folder_snapshot['drawer_reference']
            || $enrollment->cabinetReader->reader_identifier !== $enrollment->reader_identifier) {
            return response()->json(['pending' => false, 'folder' => null]);
        }

        return response()->json(['pending' => true, 'folder' => [
            'request_id' => $enrollment->request_id, ...$enrollment->folder_snapshot,
            'reader_identifier' => $enrollment->reader_identifier,
        ]]);
    }

    public function confirm(Request $request, CabinetDevice $cabinet, string $requestId): JsonResponse
    {
        abort_unless(Str::isUuid($requestId) && $requestId === Str::lower($requestId), 404);
        if (array_diff(array_keys($request->all()), ['rfid_epc', 'reader_identifier']) !== []) {
            throw ValidationException::withMessages(['payload' => 'Only the scanned tag and reader identifier are accepted.']);
        }
        if (is_string($request->input('rfid_epc'))) {
            $request->merge(['rfid_epc' => Str::upper($request->input('rfid_epc'))]);
        }
        $data = $request->validate([
            'rfid_epc' => ['required', 'string', 'regex:/\A(?:[0-9A-F]{2}){1,31}\z/'],
            'reader_identifier' => ['required', 'string', 'max:80'],
        ]);

        try {
            $enrollment = DB::transaction(function () use ($cabinet, $data, $requestId): ?CabinetFileEnrollmentRequest {
                CabinetDevice::query()->whereKey($cabinet->id)->lockForUpdate()->firstOrFail();
                $enrollment = CabinetFileEnrollmentRequest::query()->where('cabinet_device_id', $cabinet->id)
                    ->where('request_id', $requestId)->lockForUpdate()->firstOrFail();
                if ($data['reader_identifier'] !== $enrollment->reader_identifier) {
                    throw ValidationException::withMessages(['reader_identifier' => 'Use the reader assigned to this enrollment request.']);
                }
                if ($enrollment->status === CabinetFileEnrollmentRequest::ENROLLED) {
                    abort_if($enrollment->rfid_epc !== $data['rfid_epc'], 409, 'This request was already enrolled with another tag.');

                    return $enrollment;
                }
                if (! $enrollment->isPending()) {
                    if ($enrollment->status === CabinetFileEnrollmentRequest::PENDING) {
                        $enrollment->update(['status' => CabinetFileEnrollmentRequest::EXPIRED]);
                    }

                    return null;
                }
                $file = CabinetFile::query()->whereKey($enrollment->cabinet_file_id)->lockForUpdate()->firstOrFail();
                $reader = CabinetReader::query()->whereKey($enrollment->cabinet_reader_id)->lockForUpdate()->firstOrFail();
                abort_if(! $file->is_active || $file->cabinet_device_id !== $cabinet->id
                    || ! $reader->is_active || $reader->cabinet_device_id !== $cabinet->id
                    || $reader->reader_identifier !== $enrollment->reader_identifier
                    || $reader->drawer_reference !== $enrollment->folder_snapshot['drawer_reference']
                    || $file->drawer_reference !== $enrollment->folder_snapshot['drawer_reference'],
                    409, 'The folder or reader assignment changed. Start another enrollment.');
                abort_if(CabinetFile::query()->whereRaw('UPPER(rfid_epc) = ?', [$data['rfid_epc']])->where('id', '!=', $file->id)->exists(),
                    409, 'This RFID tag is already assigned to another folder.');

                $file->forceFill(['rfid_epc' => $data['rfid_epc']])->save();
                $enrollment->update(['status' => CabinetFileEnrollmentRequest::ENROLLED,
                    'rfid_epc' => $data['rfid_epc'], 'completed_at' => now()]);
                DB::table('administrative_audits')->insert([
                    'user_id' => null, 'actor' => 'Raspberry Pi · '.$cabinet->identifier,
                    'action' => 'cabinet.folder-enrollment.confirmed', 'type' => 'cabinet', 'record' => $file->file_reference,
                    'before_assignments' => null,
                    'after_assignments' => json_encode(['request_id' => $requestId, 'rfid_epc' => $data['rfid_epc'], 'reader_identifier' => $reader->reader_identifier], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return $enrollment;
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This RFID tag is already assigned to another folder.');
        }
        abort_if($enrollment === null, 409, 'This enrollment request was cancelled or expired.');

        return response()->json(['status' => 'enrolled', 'request_id' => $enrollment->request_id,
            'folder' => [...$enrollment->folder_snapshot, 'rfid_epc' => $enrollment->rfid_epc]]);
    }
}
