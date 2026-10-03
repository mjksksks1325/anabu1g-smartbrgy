<?php

namespace App\Actions;

use App\CertificateType;
use App\Models\Resident;
use App\Models\ResidentRequestRestriction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CheckRequestRestrictions
{
    public function active(Resident $resident, CertificateType $type): ?ResidentRequestRestriction
    {
        $this->expire($resident->id);

        return ResidentRequestRestriction::query()->where('resident_id', $resident->id)
            ->where('status', 'active')->whereNotNull('reviewed_at')->whereNotNull('reviewed_by')
            ->where('starts_at', '<=', now())->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn (Builder $query) => $query->whereNull('affected_document_type')->orWhere('affected_document_type', $type->value))
            ->first();
    }

    public function expire(?int $residentId = null): int
    {
        $count = 0;
        ResidentRequestRestriction::query()->where('status', 'active')->where('ends_at', '<=', now())
            ->when($residentId !== null, fn (Builder $query) => $query->where('resident_id', $residentId))
            ->select(['id', 'resident_id'])->chunkById(100, function ($restrictions) use (&$count): void {
                foreach ($restrictions as $restriction) {
                    $count += DB::transaction(function () use ($restriction): int {
                        Resident::withTrashed()->whereKey($restriction->resident_id)->lockForUpdate()->firstOrFail();
                        $locked = ResidentRequestRestriction::query()->lockForUpdate()->findOrFail($restriction->id);
                        if ($locked->status !== 'active' || $locked->ends_at === null || $locked->ends_at->isFuture()) {
                            return 0;
                        }
                        $locked->update(['status' => 'expired']);
                        app(RecordCaseActivity::class)->handle(null, 'admin.request-restrictions.expired', (string) $locked->id, ['status']);

                        return 1;
                    });
                }
            });

        return $count;
    }
}
