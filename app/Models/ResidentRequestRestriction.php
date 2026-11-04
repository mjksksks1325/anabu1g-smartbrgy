<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ResidentRequestRestrictionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $affected_document_type
 * @property string|null $resident_visible_reason
 * @property Carbon|null $next_review_at
 * @property Carbon|null $ends_at
 * @property Carbon $starts_at
 * @property Carbon|null $reviewed_at
 */
class ResidentRequestRestriction extends Model
{
    /** @use HasFactory<ResidentRequestRestrictionFactory> */
    use HasFactory;

    public const MESSAGE = 'Your request is currently on hold and requires barangay review.';

    protected $fillable = ['resident_id', 'incident_id', 'restriction_type', 'affected_document_type', 'reason_category', 'internal_reason', 'resident_visible_reason', 'starts_at', 'ends_at', 'next_review_at', 'status', 'reviewed_by', 'reviewed_at', 'created_by', 'lifted_by', 'lifted_at', 'lift_reason'];

    /**
     * What the resident may see about this hold: the documents it covers, the message staff wrote for the
     * resident (if any) and the end or next-review dates staff set. Nothing is computed, and the reason
     * category, internal reason and any linked case are never included.
     *
     * @return array{documents: string|null, message: string|null, ends_at: CarbonInterface|null, next_review_at: CarbonInterface|null}
     */
    public function residentNotice(): array
    {
        $message = trim((string) $this->resident_visible_reason);

        return [
            'documents' => $this->affected_document_type,
            'message' => $message === '' ? null : $message,
            'ends_at' => $this->ends_at,
            'next_review_at' => $this->next_review_at !== null && ($this->ends_at === null || $this->next_review_at->lt($this->ends_at))
                ? $this->next_review_at : null,
        ];
    }

    /** The hold explained in one paragraph, in the current locale, for a refused request. */
    public function residentExplanation(): string
    {
        $notice = $this->residentNotice();
        $format = fn (CarbonInterface $date): string => $date->format('F j, Y g:i A');
        $sentences = [__(self::MESSAGE)];
        if ($notice['message'] !== null) {
            $sentences[] = __('Message from the barangay: :message', ['message' => $notice['message']]);
        }
        if ($notice['ends_at'] !== null) {
            $sentences[] = __('The hold is set to end on :date.', ['date' => $format($notice['ends_at'])]);
        }
        if ($notice['next_review_at'] !== null) {
            $sentences[] = __('Barangay staff will review it on :date.', ['date' => $format($notice['next_review_at'])]);
        }
        if ($notice['ends_at'] === null && $notice['next_review_at'] === null) {
            $sentences[] = __('No end date has been set. Barangay staff will review it; ask at the Barangay Hall for help.');
        }

        return implode(' ', $sentences);
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'next_review_at' => 'datetime', 'reviewed_at' => 'datetime', 'lifted_at' => 'datetime'];
    }
}
