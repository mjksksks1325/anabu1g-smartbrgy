@extends('layouts.portal')
@section('title', __('Request a document'))
@section('band')
<x-portal.page-band title="Request a document" class="page-band-compact">
    <p class="page-band-meta"><span data-portal-i18n="May reference number na?">{{ __('May reference number na?') }}</span> <button type="button" class="btn-link" onclick="showScreen('screen-status')"><span data-portal-i18n="I-check ang status">{{ __('I-check ang status') }}</span></button></p>
</x-portal.page-band>
@endsection
@section('content')
<div id="toast-wrap" role="status" aria-live="polite"></div>
<div id="loader" style="display:none;"><div class="spinner"></div><p><span data-portal-i18n="Sandali lang...">{{ __('Sandali lang...') }}</span></p></div>

<div class="request-layout v2-request">
  <section class="portal-request-flow" id="request-flow" aria-label="{{ __('Document request') }}" data-portal-i18n-aria-label="Document request">
  @if ($requestHolds->isNotEmpty())
    <section class="alert alert-yellow request-holds" role="status" aria-labelledby="request-holds-title">
      <h2 id="request-holds-title"><span data-portal-i18n="Some requests are on hold">{{ __('Some requests are on hold') }}</span></h2>
      <ul>
        @foreach ($requestHolds as $hold)
          <li>
            <strong>@if ($hold['documents'])<span data-portal-i18n="{{ $hold['documents'] }}">{{ __($hold['documents']) }}</span>@else<span data-portal-i18n="All document requests">{{ __('All document requests') }}</span>@endif</strong>
            @if ($hold['message'])<p><span data-portal-i18n="Message from the barangay:">{{ __('Message from the barangay:') }}</span> {{ $hold['message'] }}</p>@endif
            @if ($hold['ends_at'])<p><span data-portal-i18n="Hold ends:">{{ __('Hold ends:') }}</span> <time datetime="{{ $hold['ends_at']->toIso8601String() }}">{{ $hold['ends_at']->format('F j, Y g:i A') }}</time></p>@endif
            @if ($hold['next_review_at'])<p><span data-portal-i18n="Next review by barangay staff:">{{ __('Next review by barangay staff:') }}</span> <time datetime="{{ $hold['next_review_at']->toIso8601String() }}">{{ $hold['next_review_at']->format('F j, Y g:i A') }}</time></p>@endif
            @if (! $hold['ends_at'] && ! $hold['next_review_at'])<p><span data-portal-i18n="No end date has been set. Barangay staff will review it; ask at the Barangay Hall for help.">{{ __('No end date has been set. Barangay staff will review it; ask at the Barangay Hall for help.') }}</span></p>@endif
          </li>
        @endforeach
      </ul>
    </section>
  @endif
  @if ($hasPendingOrdinaryIncidentReport)
    <p class="alert alert-blue" role="status" data-portal-i18n="A pending incident report has been linked to your resident record and is awaiting barangay review. This does not mean that a violation has been established. You may continue with your document request.">{{ __('A pending incident report has been linked to your resident record and is awaiting barangay review. This does not mean that a violation has been established. You may continue with your document request.') }}</p>
  @endif

  <div class="screen active" id="screen-terms">
    @include('portal.partials.request-steps', ['current' => 1, 'title' => 'Basahin ang terms'])
    <div class="card">
      <div class="card-header">
        <h2><span data-portal-i18n="Terms and Conditions">{{ __('Terms and Conditions') }}</span></h2>
        <p><span data-portal-i18n="Basahin hanggang dulo bago magpatuloy.">{{ __('Basahin hanggang dulo bago magpatuloy.') }}</span></p>
      </div>
      <div class="card-body">
        <section class="v2-terms-summary" aria-labelledby="terms-summary-title">
          <h3 id="terms-summary-title"><span data-portal-i18n="Ang sinasang-ayunan mo, sa madaling salita">{{ __('Ang sinasang-ayunan mo, sa madaling salita') }}</span></h3>
          <ul class="v2-list">
            <li><span data-portal-i18n="Totoo at kumpleto ang impormasyong ibibigay mo.">{{ __('Totoo at kumpleto ang impormasyong ibibigay mo.') }}</span></li>
            <li><span data-portal-i18n="Rerepasuhin muna ng barangay staff ang request bago ito aprubahan.">{{ __('Rerepasuhin muna ng barangay staff ang request bago ito aprubahan.') }}</span></li>
            <li><span data-portal-i18n="Sa Barangay Hall kukunin ang dokumento at babayaran ang fee, kung mayroon.">{{ __('Sa Barangay Hall kukunin ang dokumento at babayaran ang fee, kung mayroon.') }}</span></li>
            <li><span data-portal-i18n="Pinangangalagaan ng Barangay Anabu I-G ang personal na impormasyon ninyo alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).">{{ __('Pinangangalagaan ng Barangay Anabu I-G ang personal na impormasyon ninyo alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).') }}</span></li>
          </ul>
          <p class="v2-summary-note"><span data-portal-i18n="Nasa ibaba ang buong Terms and Conditions. Basahin ito hanggang dulo.">{{ __('Nasa ibaba ang buong Terms and Conditions. Basahin ito hanggang dulo.') }}</span></p>
        </section>
        <div class="tnc-box" id="tnc-scroll" tabindex="0" role="region" aria-label="{{ __('Terms and Conditions') }}" data-portal-i18n-aria-label="Terms and Conditions">
          <h3><span data-portal-i18n="SmartBrgy Online Portal Terms and Conditions">{{ __('SmartBrgy Online Portal Terms and Conditions') }}</span></h3>
          <p><em><span data-portal-i18n="Barangay Anabu I-G, Imus City, Cavite - Version 1.0, May 2025">{{ __('Barangay Anabu I-G, Imus City, Cavite - Version 1.0, May 2025') }}</span></em></p>
          <h4><span data-portal-i18n="Purpose">{{ __('Purpose') }}</span></h4>
          <p><span data-portal-i18n="The SmartBrgy Online Portal allows residents to submit initial requests for barangay documents online. Submission through this portal does not automatically release a document; barangay staff must still verify the request and supporting information.">{{ __('The SmartBrgy Online Portal allows residents to submit initial requests for barangay documents online. Submission through this portal does not automatically release a document; barangay staff must still verify the request and supporting information.') }}</span></p>
          <h4><span data-portal-i18n="Available Services">{{ __('Available Services') }}</span></h4>
          <ol>@foreach($services as $service)<li><span data-portal-i18n="{{ $service->value }}">{{ __($service->value) }}</span></li>@endforeach</ol>
          <h4><span data-portal-i18n="Eligibility and Verification">{{ __('Eligibility and Verification') }}</span></h4>
          <p><span data-portal-i18n="You must be a resident of Barangay Anabu I-G, provide accurate information, and personally visit the Barangay Hall when required. Some documents may require good standing or additional validation before release.">{{ __('You must be a resident of Barangay Anabu I-G, provide accurate information, and personally visit the Barangay Hall when required. Some documents may require good standing or additional validation before release.') }}</span></p>
          <h4><span data-portal-i18n="Data Privacy">{{ __('Data Privacy') }}</span></h4>
          <p><span data-portal-i18n="Barangay Anabu I-G collects your name, barangay address, email address, requested document, and purpose only for processing, verification, status updates, and official barangay records. Your information will be handled under Republic Act 10173, the Data Privacy Act of 2012.">{{ __('Barangay Anabu I-G collects your name, barangay address, email address, requested document, and purpose only for processing, verification, status updates, and official barangay records. Your information will be handled under Republic Act 10173, the Data Privacy Act of 2012.') }}</span></p>
          <h4><span data-portal-i18n="Your Responsibilities">{{ __('Your Responsibilities') }}</span></h4>
          <ol><li><span data-portal-i18n="Provide true and complete information.">{{ __('Provide true and complete information.') }}</span></li><li><span data-portal-i18n="Do not use the portal for false, misleading, or unlawful requests.">{{ __('Do not use the portal for false, misleading, or unlawful requests.') }}</span></li><li><span data-portal-i18n="Understand that online submission is subject to staff review and does not guarantee immediate approval.">{{ __('Understand that online submission is subject to staff review and does not guarantee immediate approval.') }}</span></li><li><span data-portal-i18n="Bring a valid ID and required documents when claiming the requested document.">{{ __('Bring a valid ID and required documents when claiming the requested document.') }}</span></li></ol>
          <h4><span data-portal-i18n="Fees and Validity">{{ __('Fees and Validity') }}</span></h4>
          <p><span data-portal-i18n="Applicable document fees are paid at the Barangay Hall. Online requests remain valid for 30 days from submission. Issued certificates are valid for three months from issue; First Time Jobseeker certificates and Barangay IDs are valid for one year. QR verification follows the document's validity period.">{{ __('Applicable document fees are paid at the Barangay Hall. Online requests remain valid for 30 days from submission. Issued certificates are valid for three months from issue; First Time Jobseeker certificates and Barangay IDs are valid for one year. QR verification follows the document\'s validity period.') }}</span></p>
          <h4><span data-portal-i18n="Contact">{{ __('Contact') }}</span></h4>
          <p><span data-portal-i18n="For questions, visit Barangay Hall, Barangay Anabu I-G, Imus City, Cavite, Monday to Friday, 8:00 AM to 5:00 PM.">{{ __('For questions, visit Barangay Hall, Barangay Anabu I-G, Imus City, Cavite, Monday to Friday, 8:00 AM to 5:00 PM.') }}</span></p>
          <p class="terms-meta"><span data-portal-i18n="Last updated: May 2025 | Version 1.0 | Barangay Anabu I-G">{{ __('Last updated: May 2025 | Version 1.0 | Barangay Anabu I-G') }}</span></p>
        </div>
        <p class="tnc-scroll-notice" id="tnc-notice" aria-live="polite"><span data-portal-i18n="I-scroll hanggang dulo ng terms para ma-check ang kahon sa ibaba.">{{ __('I-scroll hanggang dulo ng terms para ma-check ang kahon sa ibaba.') }}</span></p>
        <p class="unconfirmed small"><strong><span data-portal-i18n="Paalala:">{{ __('Paalala:') }}</span></strong> <span data-portal-i18n="Hindi pa kumpirmado ng barangay ang office hours na nakasulat sa terms.">{{ __('Hindi pa kumpirmado ng barangay ang office hours na nakasulat sa terms.') }}</span></p>
        <div class="checkbox-row" style="margin-top:18px;">
          <input type="checkbox" id="tnc-agree" disabled onchange="onTncCheck()">
          <label for="tnc-agree"><span data-portal-i18n="I have read and understood the">{{ __('I have read and understood the') }}</span> <strong><span data-portal-i18n="Terms and Conditions">{{ __('Terms and Conditions') }}</span></strong> <span data-portal-i18n="of the SmartBrgy Portal. I agree to the collection and use of my personal information under">{{ __('of the SmartBrgy Portal. I agree to the collection and use of my personal information under') }}</span> <strong><span data-portal-i18n="Republic Act 10173 (Data Privacy Act of 2012)">{{ __('Republic Act 10173 (Data Privacy Act of 2012)') }}</span></strong> <span data-portal-i18n="and Barangay Anabu I-G policies.">{{ __('and Barangay Anabu I-G policies.') }}</span></label>
        </div>
        <div class="btn-row">
          <button type="button" class="btn btn-green btn-full" id="btn-proceed-terms" onclick="proceedFromTerms()" disabled><span data-portal-i18n="I agree and continue">{{ __('I agree and continue') }}</span></button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-doctype">
    @include('portal.partials.request-steps', ['current' => 2, 'title' => 'Piliin ang dokumento'])
    <div class="card">
      <div class="card-header">
        <h2><span data-portal-i18n="Piliin ang dokumento">{{ __('Piliin ang dokumento') }}</span></h2>
        <p><span data-portal-i18n="Isang dokumento bawat request.">{{ __('Isang dokumento bawat request.') }}</span></p>
      </div>
      <div class="card-body">
                <div class="cert-grid" id="cert-type-grid">
          @foreach($services as $service)
          <button type="button" class="cert-btn" aria-pressed="false" onclick="selectDoc('{{ $service->portalCode() }}',this)"><span class="label"><span data-portal-i18n="{{ $service->value }}">{{ __($service->value) }}</span></span><span class="fee"><span class="sr-only"><span data-portal-i18n="Fee:">{{ __('Fee:') }}</span> </span><span data-portal-i18n="{{ $service->feeLabel() }}">{{ __($service->feeLabel()) }}</span>@if(! $service->feeConfirmed()) <span class="tag-unconfirmed"><span data-portal-i18n="To be confirmed">{{ __('To be confirmed') }}</span></span>@endif</span><span class="cert-validity">@if($service->validityPeriodInMonths() === 12)<span data-portal-i18n="Valid nang 1 taon mula pagka-issue">{{ __('Valid nang 1 taon mula pagka-issue') }}</span>@else<span data-portal-i18n="Valid nang :months buwan mula pagka-issue" data-portal-params="{{ json_encode(['months' => $service->validityPeriodInMonths()]) }}">{{ __('Valid nang :months buwan mula pagka-issue', ['months' => $service->validityPeriodInMonths()]) }}</span>@endif</span><span class="cert-selected"><span aria-hidden="true">&#10003;</span> <span data-portal-i18n="Napili">{{ __('Napili') }}</span></span><span class="cert-check" aria-hidden="true"></span></button>
          @endforeach
        </div>
        <div class="v2-callout v2-callout-warning">
          <svg class="v2-callout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/></svg>
          <p><strong><span data-portal-i18n="Hindi pa kumpirmado:">{{ __('Hindi pa kumpirmado:') }}</span></strong> <span data-portal-i18n="Business Clearance fee at processing time. Regular Barangay ID ang fee na nakalista. Sa Barangay Hall babayaran ang fee.">{{ __('Business Clearance fee at processing time. Regular Barangay ID ang fee na nakalista. Sa Barangay Hall babayaran ang fee.') }}</span></p>
        </div>
        <p id="doc-selected-info" class="selected-document" style="display:none;" aria-live="polite"><span data-portal-i18n="Napili:">{{ __('Napili:') }}</span> <strong id="doc-sel-label"></strong> &middot; <span id="doc-sel-fee"></span></p>
        <div class="btn-row btn-row-split">
          <button type="button" class="btn btn-outline" onclick="goBack('screen-terms')"><span data-portal-i18n="Bumalik">{{ __('Bumalik') }}</span></button>
          <button type="button" class="btn btn-green" id="btn-proceed-doc" onclick="proceedFromDoc()" disabled><span data-portal-i18n="Next">{{ __('Next') }}</span></button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-form">
    @include('portal.partials.request-steps', ['current' => 3, 'title' => 'I-check ang details'])
    <div class="card">
      <div class="card-header">
        <h2><span data-portal-i18n="I-check ang details">{{ __('I-check ang details') }}</span></h2>
        <p class="request-subject"><span><span data-portal-i18n="Nire-request:">{{ __('Nire-request:') }}</span></span> <strong id="form-doc-label"></strong> <span id="form-doc-fee"></span></p>
      </div>
      <form class="card-body" id="resident-details-form" autocomplete="off" novalidate onsubmit="event.preventDefault();goToAttachment()">
        <div id="portal-form-error" class="alert alert-red" role="alert" hidden></div>
        <p class="muted small" style="margin-bottom:18px;"><span data-portal-i18n="Galing sa resident record ninyo ang pangalan, address, email, at petsa ng kapanganakan. Hindi ito mae-edit dito. Kung may mali, pumunta sa Barangay Hall para magpa-correct.">{{ __('Galing sa resident record ninyo ang pangalan, address, email, at petsa ng kapanganakan. Hindi ito mae-edit dito. Kung may mali, pumunta sa Barangay Hall para magpa-correct.') }}</span></p>

        <div class="form-group">
          <label class="form-label" for="f-name"><span data-portal-i18n="Full name">{{ __('Full name') }}</span></label>
          <input class="form-input" id="f-name" readonly aria-readonly="true" required maxlength="255" autocomplete="off">
          <p class="field-error" id="f-name-error" hidden data-portal-message></p>
        </div>
        <div class="form-group">
          <label class="form-label" for="f-address"><span data-portal-i18n="Address">{{ __('Address') }}</span></label>
          <input class="form-input" id="f-address" readonly aria-readonly="true" required maxlength="1000" autocomplete="off">
          <p class="field-error" id="f-address-error" hidden data-portal-message></p>
        </div>
        <div class="form-group">
          <label class="form-label" for="f-email"><span data-portal-i18n="Email address">{{ __('Email address') }}</span></label>
          <input class="form-input" id="f-email" readonly aria-readonly="true" maxlength="255" inputmode="email" autocapitalize="none" spellcheck="false" type="email" autocomplete="off" required aria-describedby="f-email-help">
          <p class="field-error" id="f-email-error" hidden data-portal-message></p>
          <p class="form-note" id="f-email-help"><span data-portal-i18n="Dito makikipag-ugnayan ang barangay tungkol sa request.">{{ __('Dito makikipag-ugnayan ang barangay tungkol sa request.') }}</span></p>
        </div>
        <div class="form-group">
          <label class="form-label" for="f-dob"><span data-portal-i18n="Date of birth">{{ __('Date of birth') }}</span></label>
          <input class="form-input" id="f-dob" readonly aria-readonly="true" type="date" autocomplete="off" max="{{ now()->toDateString() }}" required>
          <p class="field-error" id="f-dob-error" hidden data-portal-message></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-purpose"><span data-portal-i18n="Purpose">{{ __('Purpose') }}</span> <span class="req"><span data-portal-i18n="(required)">{{ __('(required)') }}</span></span></label>
          <input class="form-input" id="f-purpose" required maxlength="255" enterkeyhint="next" aria-describedby="f-purpose-help">
          <p class="field-error" id="f-purpose-error" hidden data-portal-message></p>
          <p class="form-note" id="f-purpose-help"><span data-portal-i18n="Saan gagamitin ang dokumento? Halimbawa: trabaho, scholarship, o bank requirement.">{{ __('Saan gagamitin ang dokumento? Halimbawa: trabaho, scholarship, o bank requirement.') }}</span></p>
        </div>
        <div id="business-field" style="display:none;" class="form-group">
          <label class="form-label" for="f-business"><span data-portal-i18n="Business name">{{ __('Business name') }}</span> <span class="req"><span data-portal-i18n="(required)">{{ __('(required)') }}</span></span></label>
          <input class="form-input" id="f-business" maxlength="255" autocomplete="off" enterkeyhint="done">
          <p class="field-error" id="f-business-error" hidden data-portal-message></p>
        </div>

        <p class="muted small"><span data-portal-i18n="Gagamitin lang ang impormasyong ito para sa pagproseso ng request, alinsunod sa RA 10173.">{{ __('Gagamitin lang ang impormasyong ito para sa pagproseso ng request, alinsunod sa RA 10173.') }}</span></p>
        <div class="btn-row btn-row-split">
          <button type="button" class="btn btn-outline" onclick="goBack('screen-doctype')"><span data-portal-i18n="Bumalik">{{ __('Bumalik') }}</span></button>
          <button type="submit" class="btn btn-green"><span data-portal-i18n="Next">{{ __('Next') }}</span></button>
        </div>
      </form>
    </div>
  </div>

  <div class="screen" id="screen-attachment">
    @include('portal.partials.request-steps', ['current' => 4, 'title' => 'Mag-upload ng valid ID (optional)'])
    <div class="card">
      <div class="card-header">
        <h2><span data-portal-i18n="Mag-upload ng valid ID">{{ __('Mag-upload ng valid ID') }}</span> <span class="muted"><span data-portal-i18n="(optional)">{{ __('(optional)') }}</span></span></h2>
        <p><span data-portal-i18n="Hindi required, pero makakatulong ito para mas mabilis ma-verify ng staff ang request.">{{ __('Hindi required, pero makakatulong ito para mas mabilis ma-verify ng staff ang request.') }}</span></p>
      </div>
      <div class="card-body">
        <ul class="plain-list" style="margin-bottom:18px;">
          <li><span data-portal-i18n="Supported image files only, up to 5 MB. Documents are not accepted.">{{ __('Supported image files only, up to 5 MB. Documents are not accepted.') }}</span></li>
          <li><span data-portal-i18n="Kunan nang buo ang ID. Dapat malinaw ang pangalan at litrato, walang silaw o blur.">{{ __('Kunan nang buo ang ID. Dapat malinaw ang pangalan at litrato, walang silaw o blur.') }}</span></li>
          <li><span data-portal-i18n="Dalhin pa rin ang original na valid ID pagkuha ng dokumento sa Barangay Hall.">{{ __('Dalhin pa rin ang original na valid ID pagkuha ng dokumento sa Barangay Hall.') }}</span></li>
        </ul>
        <label class="sr-only" for="f-attachment"><span data-portal-i18n="Litrato ng valid ID">{{ __('Litrato ng valid ID') }}</span></label>
        <input type="file" id="f-attachment" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,.avif,.heic,.heif,image/jpeg,image/png,image/gif,image/bmp,image/webp,image/avif,image/heic,image/heif" class="sr-only" tabindex="-1" onchange="previewAttachment(this)">
        <button type="button" id="att-dropzone" class="upload-box" aria-describedby="attachment-help" onclick="document.getElementById('f-attachment').click()" ondragover="event.preventDefault();this.classList.add('is-dragover')" ondragleave="this.classList.remove('is-dragover')" ondrop="handleAttachmentDrop(event)">
          <strong><span data-portal-i18n="Pumili ng litrato ng ID">{{ __('Pumili ng litrato ng ID') }}</span></strong>
          <span id="attachment-help"><span data-portal-i18n="You can also take a photo with your phone camera. Supported image files only, up to 5 MB.">{{ __('You can also take a photo with your phone camera. Supported image files only, up to 5 MB.') }}</span></span>
        </button>
        <div id="att-preview" class="attachment-preview" style="display:none;">
          <div class="attachment-preview-inner">
            <img id="att-preview-img" alt="{{ __('Preview ng in-upload na ID') }}" data-portal-i18n-alt="Preview ng in-upload na ID">
            <div>
              <p class="attachment-status" role="status"><span data-portal-i18n="Naka-attach na ang litrato">{{ __('Naka-attach na ang litrato') }}</span></p>
              <p id="att-preview-name"></p>
              <button type="button" class="btn btn-outline btn-small" onclick="clearAttachment()"><span data-portal-i18n="Alisin ang litrato">{{ __('Alisin ang litrato') }}</span></button>
            </div>
          </div>
        </div>
        <div class="btn-row btn-row-split">
          <button type="button" class="btn btn-outline" onclick="goBack('screen-form')"><span data-portal-i18n="Bumalik">{{ __('Bumalik') }}</span></button>
          <button type="button" class="btn btn-green" onclick="goToReview()"><span data-portal-i18n="Next">{{ __('Next') }}</span></button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-review">
    @include('portal.partials.request-steps', ['current' => 5, 'title' => 'I-review at i-submit'])
    <div class="card">
      <div class="card-header">
        <h2><span data-portal-i18n="I-review ang request">{{ __('I-review ang request') }}</span></h2>
        <p><span data-portal-i18n="I-check kung tama ang lahat. Hindi na ito mae-edit online pagka-submit.">{{ __('I-check kung tama ang lahat. Hindi na ito mae-edit online pagka-submit.') }}</span></p>
      </div>
      <div class="card-body">
        <div id="review-summary"></div>
        <p class="small" style="margin-top:12px;"><button type="button" class="btn-link" onclick="goBack('screen-doctype')"><span data-portal-i18n="Palitan ang dokumento">{{ __('Palitan ang dokumento') }}</span></button> &middot; <button type="button" class="btn-link" onclick="goBack('screen-form')"><span data-portal-i18n="Palitan ang purpose">{{ __('Palitan ang purpose') }}</span></button></p>
        <div class="btn-row btn-row-split">
          <button type="button" class="btn btn-outline" onclick="goBack('screen-attachment')"><span data-portal-i18n="Bumalik">{{ __('Bumalik') }}</span></button>
          <button type="button" class="btn btn-green" id="submit-request-button" onclick="submitRequest()"><span data-portal-i18n="I-submit ang request">{{ __('I-submit ang request') }}</span></button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-confirm">
    <div class="card">
      <div class="card-header confirm-head">
        <span class="confirm-mark" aria-hidden="true"></span>
        <div>
          <h2><span data-portal-i18n="Na-submit na ang request ninyo">{{ __('Na-submit na ang request ninyo') }}</span></h2>
          <p><span data-portal-i18n="Nire-review na ito ng barangay staff.">{{ __('Nire-review na ito ng barangay staff.') }}</span></p>
        </div>
      </div>
      <div class="card-body">
        <div class="confirm-ticket">
          <div>
            <p class="confirm-ticket-label"><span data-portal-i18n="Reference number">{{ __('Reference number') }}</span></p>
            <p class="confirm-code" id="conf-code"></p>
          </div>
          <button class="btn btn-outline btn-small copy-code-button" type="button" onclick="copyReferenceCode()"><span data-portal-i18n="Kopyahin ang code">{{ __('Kopyahin ang code') }}</span></button>
        </div>
        <p class="confirm-hint"><span data-portal-i18n="I-screenshot o isulat ang reference number. Kailangan ito pagkuha ng dokumento.">{{ __('I-screenshot o isulat ang reference number. Kailangan ito pagkuha ng dokumento.') }}</span></p>

        <h3 class="fieldset-title" style="margin-top:24px;"><span data-portal-i18n="Buod ng request">{{ __('Buod ng request') }}</span></h3>
        <div id="conf-summary"></div>

        <h3 class="fieldset-title" style="margin-top:24px;"><span data-portal-i18n="Susunod na gagawin">{{ __('Susunod na gagawin') }}</span></h3>
        <ol class="confirm-steps">
          <li><span data-portal-i18n="Bantayan ang status sa My requests.">{{ __('Bantayan ang status sa My requests.') }}</span></li>
          <li><span data-portal-i18n="Kapag">{{ __('Kapag') }}</span> <strong><span data-portal-i18n="Ready for release">{{ __('Ready for release') }}</span></strong> <span data-portal-i18n="na, pumunta sa Barangay Hall ng Anabu I-G. Valid ang online request nang 30 araw mula sa pag-submit.">{{ __('na, pumunta sa Barangay Hall ng Anabu I-G. Valid ang online request nang 30 araw mula sa pag-submit.') }}</span></li>
          <li><span data-portal-i18n="Dalhin ang 1 valid government ID at ang reference number:">{{ __('Dalhin ang 1 valid government ID at ang reference number:') }}</span> <strong id="conf-code-mini"></strong></li>
          <li><span data-portal-i18n="Bayaran sa Barangay Hall ang fee, kung mayroon.">{{ __('Bayaran sa Barangay Hall ang fee, kung mayroon.') }}</span></li>
        </ol>
        <div class="btn-row">
          <a class="btn btn-green" href="{{ route('portal.account') }}"><span data-portal-i18n="Pumunta sa My requests">{{ __('Pumunta sa My requests') }}</span></a>
          <button type="button" class="btn btn-outline" onclick="newRequest()"><span data-portal-i18n="Mag-request ng iba pang dokumento">{{ __('Mag-request ng iba pang dokumento') }}</span></button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-status">
    <div class="card">
      <div class="card-header">
        <h2><span data-portal-i18n="I-check ang status">{{ __('I-check ang status') }}</span></h2>
        <p><span data-portal-i18n="Ilagay ang reference number ng request. Makikita rin ang lahat ng request ninyo sa">{{ __('Ilagay ang reference number ng request. Makikita rin ang lahat ng request ninyo sa') }}</span> <a href="{{ route('portal.account') }}"><span data-portal-i18n="My requests">{{ __('My requests') }}</span></a>.</p>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label" for="status-code"><span data-portal-i18n="Reference number">{{ __('Reference number') }}</span></label>
          <div class="status-search-row">
            <input class="form-input" id="status-code" maxlength="32" autocapitalize="characters" autocomplete="off" spellcheck="false" enterkeyhint="search" placeholder="{{ __('REQ-2026-XXXXXX') }}" data-portal-i18n-placeholder="REQ-2026-XXXXXX" onkeydown="if(event.key==='Enter')checkStatus()">
            <button type="button" class="btn btn-green" onclick="checkStatus()"><span data-portal-i18n="Hanapin">{{ __('Hanapin') }}</span></button>
          </div>
        </div>
        <div id="status-result" role="status" aria-live="polite" style="display:none;"></div>
        <div class="btn-row">
          <button type="button" class="btn btn-outline" onclick="showScreen('screen-terms')"><span data-portal-i18n="Bumalik sa simula">{{ __('Bumalik sa simula') }}</span></button>
        </div>
      </div>
    </div>
  </div>

  </section>

  <aside class="v2-request-aside" id="request-progress" aria-label="{{ __('Ang request mo') }}" data-portal-i18n-aria-label="Ang request mo">
    <section class="v2-card" aria-labelledby="request-progress-title">
      <h2 id="request-progress-title"><span data-portal-i18n="Mga hakbang">{{ __('Mga hakbang') }}</span></h2>
      <ol class="v2-steps">
        @foreach(['Terms', 'Dokumento', 'Layunin', 'Litrato ng ID', 'I-review'] as $index => $label)
          <li data-step="{{ $index + 1 }}" @class(['is-current' => $index === 0]) @if($index === 0) aria-current="step" @endif><span><span data-portal-i18n="{{ $label }}">{{ __($label) }}</span><span class="v2-step-now" @if($index !== 0) hidden @endif><span data-portal-i18n="Nandito ka">{{ __('Nandito ka') }}</span></span></span></li>
        @endforeach
      </ol>
    </section>
    <section class="v2-card" aria-labelledby="request-summary-title">
      <h2 id="request-summary-title"><span data-portal-i18n="Ang request mo">{{ __('Ang request mo') }}</span></h2>
      <dl class="v2-summary">
        <div><dt><span data-portal-i18n="Dokumento">{{ __('Dokumento') }}</span></dt><dd id="request-summary-document"><span data-portal-i18n="Wala pang napili">{{ __('Wala pang napili') }}</span></dd></div>
        <div><dt><span data-portal-i18n="Fee">{{ __('Fee') }}</span></dt><dd id="request-summary-fee">—</dd></div>
      </dl>
      <p class="v2-summary-note"><span data-portal-i18n="Babayaran sa Barangay Hall pagkuha ng dokumento.">{{ __('Babayaran sa Barangay Hall pagkuha ng dokumento.') }}</span></p>
    </section>
  </aside>
</div>
@endsection

@push('scripts')
<script>
const API = '';
const DOC_TYPES = {{ Illuminate\Support\Js::from(collect($services)->mapWithKeys(fn ($service) => [$service->portalCode() => ['label' => $service->value, 'fee' => __($service->feeLabel()).($service->feeConfirmed() ? '' : ' ('.__('To be confirmed').')')]])) }};
window.RESIDENT_PORTAL = {{ Illuminate\Support\Js::from(['authenticated' => auth('resident')->check(), 'loginUrl' => route('portal.login'), 'identityUrl' => route('portal.identity'), 'startRequest' => true, 'service' => request()->string('service')->toString()]) }};

let selectedDocId = null;
let tncScrolled = false;
let lastCode = '';
let _portalDark = false;

// Remove drafts saved by earlier versions of the portal.
const _SK = 'smartbrgy_session';

document.getElementById('tnc-scroll').addEventListener('scroll', function () {
    if (this.scrollTop + this.clientHeight >= this.scrollHeight - 30) {
        tncScrolled = true;
        document.getElementById('tnc-agree').disabled = false;
        const notice = document.getElementById('tnc-notice');
        portalSetText(notice, 'Nabasa na ang terms. Puwede nang i-check ang kahon.');
        notice.classList.add('is-read');
    }
});

function onTncCheck() {
    document.getElementById('btn-proceed-terms').disabled = !document.getElementById('tnc-agree').checked;
}

async function proceedFromTerms() {
    if (!document.getElementById('tnc-agree').checked) return;
    if (!await ensurePortalIdentity()) return;

    showScreen('screen-doctype');
    const service = window.RESIDENT_PORTAL?.service;
    if (service && DOC_TYPES[service]) {
        const button = document.querySelector(`.cert-btn[onclick*="'${service}'"]`);
        if (button) { selectDoc(service, button); await proceedFromDoc(); }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initializePortalSession();
    initializePortalNavigation();
});
</script>
<script src="{{ asset('js/portal-form.js') }}?v={{ filemtime(public_path('js/portal-form.js')) }}"></script>
<script src="{{ asset('js/portal-session.js') }}?v={{ filemtime(public_path('js/portal-session.js')) }}"></script>
<script src="{{ asset('js/attachments.js') }}?v={{ filemtime(public_path('js/attachments.js')) }}"></script>
<script src="{{ asset('js/portal-ui.js') }}?v={{ filemtime(public_path('js/portal-ui.js')) }}"></script>
<script src="{{ asset('js/document-request.js') }}?v={{ filemtime(public_path('js/document-request.js')) }}"></script>
<script src="{{ asset('js/status-checker.js') }}?v={{ filemtime(public_path('js/status-checker.js')) }}"></script>
<script src="{{ asset('js/request-progress.js') }}?v={{ filemtime(public_path('js/request-progress.js')) }}"></script>
@endpush
