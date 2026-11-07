<?php

namespace App\Actions;

use App\CertificateType;
use App\Models\Resident;
use App\Models\ResidentRequestRestriction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CheckRequestRestrictions
{
    public function active(Resident $resident, CertificateType $type): ?ResidentRequestRestriction
    {
        $this->expire($resident->id);

        return $this->inForce($resident)
            ->where(fn (Builder $query) => $query->whereNull('affected_document_type')->orWhere('affected_document_type', $type->value))
            ->first();
    }

    /**
     * A reviewed hold in force that covers every document. The staff-only certificates (AdditionalCertificateType)
     * cannot be chosen as a hold's affected document, so only these holds apply to them.
     */
    public function activeForEveryDocument(Resident $resident): ?ResidentRequestRestriction
    {
        $this->expire($resident->id);

        return $this->inForce($resident)->whereNull('affected_document_type')->first();
    }

    /**
     * Every reviewed hold in force for the resident, for the notice on the request page.
     *
     * @return Collection<int, ResidentRequestRestriction>
     */
    public function allActive(Resident $resident): Collection
    {
        $this->expire($resident->id);

        return $this->inForce($resident)->orderByRaw('affected_document_type IS NOT NULL')->orderBy('affected_document_type')->get();
    }

    /** @return Builder<ResidentRequestRestriction> */
    private function inForce(Resident $resident): Builder
    {
        return ResidentRequestRestriction::query()->where('resident_id', $resident->id)
            ->where('status', 'active')->whereNotNull('reviewed_at')->whereNotNull('reviewed_by')
            ->where('starts_at', '<=', now())->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
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
