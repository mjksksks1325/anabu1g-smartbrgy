<?php

namespace App\Http\Requests;

use App\Models\Resident;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreResidentApplicationRequest extends FormRequest
{
    /** Youngest age that may apply online. Younger residents are registered by staff at the Barangay Hall. */
    public const MINIMUM_ONLINE_AGE = 16;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The latest birth date that is old enough to apply today. Without overflow, so a 29 February birthday
     * in a non-leap year counts from 1 March, the day the applicant has actually lived the full years.
     */
    public static function latestBirthDate(): CarbonInterface
    {
        return today()->subYearsNoOverflow(self::MINIMUM_ONLINE_AGE);
    }

    /**
     * Email uniqueness is checked when staff approve the application (ResidentApplicationReviewController),
     * not here, so this public form never tells a visitor whether an email address already has an account.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', "regex:/^[\\pL\\pM .'-]+$/u"],
            'middle_name' => ['nullable', 'string', 'max:100', "regex:/^[\\pL\\pM .'-]+$/u"],
            'last_name' => ['required', 'string', 'max:100', "regex:/^[\\pL\\pM .'-]+$/u"],
            'suffix' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:'.self::latestBirthDate()->toDateString()],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'civil_status' => ['required', Rule::in(['Single', 'Married', 'Widowed', 'Separated'])],
            'purok' => ['required', 'string', Rule::exists('puroks', 'name')->where('is_active', true)],
            'address' => ['required', 'string', 'max:1000'],
            'contact_number' => ['required', 'string', 'regex:'.Resident::MOBILE_NUMBER_PATTERN],
            'residency_type' => ['required', Rule::in(['Homeowner', 'Renter', 'Boarder'])],
            'email' => ['required', 'string', 'email', 'max:255'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif', 'extensions:jpg,jpeg,png,gif,bmp,webp,avif,heic,heif', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'privacy_notice_accepted' => ['accepted'],
            ...array_fill_keys(['status', 'role', 'is_active', 'resident_id', 'reviewed_by', 'reviewed_at', 'reference', 'remarks', 'staff_permissions', 'portal_registration_hash', 'is_in_good_standing', 'is_verified_indigent', 'password'], ['prohibited']),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => __('Online applicants must be at least 16 years old. For a younger resident, please visit the Barangay Hall.'),
            'date_of_birth.after_or_equal' => __('Enter a valid date of birth.'),
            'contact_number.regex' => __('Enter a mobile number as 09XXXXXXXXX or +639XXXXXXXXX.'),
            'privacy_notice_accepted.accepted' => __('Read the Data Privacy Notice and tick the box to agree before you submit.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'address', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $value = Str::squish($this->input($field));
                $this->merge([$field => $field === 'email' ? Str::lower($value) : $value]);
            }
        }
        if (is_string($this->input('contact_number'))) {
            $this->merge(['contact_number' => Resident::normalizeContactNumber($this->input('contact_number'))]);
        }
    }
}
