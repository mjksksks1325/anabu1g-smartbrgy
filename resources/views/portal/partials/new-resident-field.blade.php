@php
    $fieldId = $field === 'date_of_birth' ? 'application-birth' : 'application-'.$field;
    $value = old($field, $field === 'email' ? $application?->email : ($application?->details[$field] ?? ''));
    $value = is_string($value) ? $value : '';
    $required = !in_array($field, ['middle_name', 'suffix', 'nationality', 'contact_number']);
    $choices = ['gender' => ['Male', 'Female'], 'civil_status' => ['Single', 'Married', 'Widowed', 'Separated'], 'residency_type' => ['Homeowner', 'Renter', 'Boarder'], 'purok' => $puroks][$field] ?? null;
@endphp
<div @class(['form-group', 'new-resident-field-wide' => $field === 'address'])>
    <label class="form-label" for="{{ $fieldId }}"><span data-portal-i18n="{{ $label }}">{{ __($label) }}</span> @if($required)<span aria-hidden="true">*</span>@else<span class="new-resident-optional" data-portal-i18n="Optional">{{ __('Optional') }}</span>@endif</label>
    @if($choices !== null)
        <select class="form-input" id="{{ $fieldId }}" name="{{ $field }}" required @error($field) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror>
            <option value="" data-portal-i18n="Select an option">{{ __('Select an option') }}</option>
            @foreach($choices as $choice)<option value="{{ $choice }}" @selected($value === $choice) @if($field !== 'purok') data-portal-i18n="{{ $choice }}" @endif>{{ $field === 'purok' ? $choice : __($choice) }}</option>@endforeach
        </select>
    @elseif($field === 'address')
        <textarea class="form-input" id="{{ $fieldId }}" name="address" maxlength="1000" rows="3" required @error($field) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror>{{ $value }}</textarea>
    @else
        <input class="form-input" id="{{ $fieldId }}" name="{{ $field }}" type="{{ ['date_of_birth' => 'date', 'email' => 'email', 'contact_number' => 'tel'][$field] ?? 'text' }}" value="{{ $value }}" maxlength="{{ $field === 'email' ? 255 : ($field === 'suffix' ? 20 : 100) }}" @if($field === 'date_of_birth') max="{{ today()->toDateString() }}" @endif @required($required) @error($field) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror>
    @endif
    @error($field)<p class="field-error" id="{{ $fieldId }}-error" data-portal-message>{{ $message }}</p>@enderror
</div>
