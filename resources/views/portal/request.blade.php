@extends('layouts.portal')
@section('title', 'Request a document')
@section('band')
<x-portal.page-band title="Request a document">
    <p>Limang hakbang: basahin ang terms, piliin ang dokumento, i-check ang details, mag-upload ng valid ID kung gusto, at i-review bago i-submit.</p>
    <p class="page-band-meta">May reference number na? <button type="button" class="btn-link" onclick="showScreen('screen-status')">I-check ang status</button></p>
</x-portal.page-band>
@endsection
@section('content')
<div id="toast-wrap" role="status" aria-live="polite"></div>
<div id="loader" style="display:none;"><div class="spinner"></div><p>Sandali lang...</p></div>

<div class="request-layout">
  <section class="portal-request-flow" id="request-flow" aria-label="Document request">

  <div class="screen active" id="screen-terms">
    @include('portal.partials.request-steps', ['current' => 1, 'title' => 'Basahin ang terms'])
    <div class="card">
      <div class="card-header">
        <h2>Terms and Conditions</h2>
        <p>Basahin hanggang dulo bago magpatuloy.</p>
      </div>
      <div class="card-body">
        <p class="alert alert-blue">Pinangangalagaan ng Barangay Anabu I-G ang personal na impormasyon ninyo alinsunod sa Republic Act 10173 (Data Privacy Act of 2012).</p>
        <div class="tnc-box" id="tnc-scroll" tabindex="0" role="region" aria-label="Terms and Conditions">
          <h3>SmartBrgy Online Portal Terms and Conditions</h3>
          <p><em>Barangay Anabu I-G, Imus City, Cavite - Version 1.0, May 2025</em></p>
          <h4>Purpose</h4>
          <p>The SmartBrgy Online Portal allows residents to submit initial requests for barangay documents online. Submission through this portal does not automatically release a document; barangay staff must still verify the request and supporting information.</p>
          <h4>Available Services</h4>
          <ol><li>Barangay Clearance</li><li>Certificate of Residency</li><li>Certificate of Indigency</li><li>Barangay ID</li><li>First Time Jobseeker Certificate</li><li>Business Clearance</li></ol>
          <h4>Eligibility and Verification</h4>
          <p>You must be a resident of Barangay Anabu I-G, provide accurate information, and personally visit the Barangay Hall when required. Some documents may require good standing or additional validation before release.</p>
          <h4>Data Privacy</h4>
          <p>Barangay Anabu I-G collects your name, barangay address, email address, requested document, and purpose only for processing, verification, status updates, and official barangay records. Your information will be handled under Republic Act 10173, the Data Privacy Act of 2012.</p>
          <h4>Your Responsibilities</h4>
          <ol><li>Provide true and complete information.</li><li>Do not use the portal for false, misleading, or unlawful requests.</li><li>Understand that online submission is subject to staff review and does not guarantee immediate approval.</li><li>Bring a valid ID and required documents when claiming the requested document.</li></ol>
          <h4>Fees and Validity</h4>
          <p>Applicable document fees are paid at the Barangay Hall. Online requests remain valid for 30 days from submission. After that period, a new request may be required.</p>
          <h4>Contact</h4>
          <p>For questions, visit Barangay Hall, Barangay Anabu I-G, Imus City, Cavite, Monday to Friday, 8:00 AM to 5:00 PM.</p>
          <p class="terms-meta">Last updated: May 2025 | Version 1.0 | Barangay Anabu I-G</p>
        </div>
        <p class="tnc-scroll-notice" id="tnc-notice" aria-live="polite">I-scroll hanggang dulo ng terms para ma-check ang kahon sa ibaba.</p>
        <p class="unconfirmed small"><strong>Paalala:</strong> Hindi pa kumpirmado ng barangay ang office hours na nakasulat sa terms.</p>
        <div class="checkbox-row" style="margin-top:18px;">
          <input type="checkbox" id="tnc-agree" disabled onchange="onTncCheck()">
          <label for="tnc-agree">I have read and understood the <strong>Terms and Conditions</strong> of the SmartBrgy Portal. I agree to the collection and use of my personal information under <strong>Republic Act 10173 (Data Privacy Act of 2012)</strong> and Barangay Anabu I-G policies.</label>
        </div>
        <div class="btn-row">
          <button type="button" class="btn btn-green btn-full" id="btn-proceed-terms" onclick="proceedFromTerms()" disabled>I agree and continue</button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-doctype">
    @include('portal.partials.request-steps', ['current' => 2, 'title' => 'Piliin ang dokumento'])
    <div class="card">
      <div class="card-header">
        <h2>Piliin ang dokumento</h2>
        <p>Isang dokumento bawat request.</p>
      </div>
      <div class="card-body">
        <p class="unconfirmed small" style="margin-bottom:16px;"><strong>Hindi pa kumpirmado:</strong> Ang fees ay mula sa system at kailangan pang kumpirmahin ng barangay. Hindi pa rin kumpirmado ang processing time. Sa Barangay Hall babayaran ang fee.</p>
        <div class="cert-grid" id="cert-type-grid">
          @foreach($services as $service)
          <button type="button" class="cert-btn" aria-pressed="false" onclick="selectDoc('{{ $service->portalCode() }}',this)"><span class="label">{{ $service->value }}</span><span class="fee">Fee: {{ $service->feeLabel() }}</span><span class="cert-selected">Napili</span><span class="cert-check" aria-hidden="true"></span></button>
          @endforeach
        </div>
        <p id="doc-selected-info" class="selected-document" style="display:none;" aria-live="polite">Napili: <strong id="doc-sel-label"></strong> &middot; <span id="doc-sel-fee"></span></p>
        <div class="btn-row btn-row-split">
          <button type="button" class="btn btn-outline" onclick="goBack('screen-terms')">Bumalik</button>
          <button type="button" class="btn btn-green" id="btn-proceed-doc" onclick="proceedFromDoc()" disabled>Next</button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-form">
    @include('portal.partials.request-steps', ['current' => 3, 'title' => 'I-check ang details'])
    <div class="card">
      <div class="card-header">
        <h2>I-check ang details</h2>
        <p class="request-subject"><span>Nire-request:</span> <strong id="form-doc-label"></strong> <span id="form-doc-fee"></span></p>
      </div>
      <form class="card-body" id="resident-details-form" autocomplete="off" novalidate onsubmit="event.preventDefault();goToAttachment()">
        <div id="portal-form-error" class="alert alert-red" role="alert" hidden></div>
        <p class="muted small" style="margin-bottom:18px;">Galing sa resident record ninyo ang pangalan, address, email, at petsa ng kapanganakan. Hindi ito mae-edit dito. Kung may mali, pumunta sa Barangay Hall para magpa-correct.</p>

        <div class="form-group">
          <label class="form-label" for="f-name">Full name</label>
          <input class="form-input" id="f-name" readonly aria-readonly="true" required maxlength="255" autocomplete="off">
          <p class="field-error" id="f-name-error" hidden></p>
        </div>
        <div class="form-group">
          <label class="form-label" for="f-address">Address</label>
          <input class="form-input" id="f-address" readonly aria-readonly="true" required maxlength="1000" autocomplete="off">
          <p class="field-error" id="f-address-error" hidden></p>
        </div>
        <div class="form-group">
          <label class="form-label" for="f-email">Email address</label>
          <input class="form-input" id="f-email" readonly aria-readonly="true" maxlength="255" inputmode="email" autocapitalize="none" spellcheck="false" type="email" autocomplete="off" required aria-describedby="f-email-help">
          <p class="field-error" id="f-email-error" hidden></p>
          <p class="form-note" id="f-email-help">Dito makikipag-ugnayan ang barangay tungkol sa request.</p>
        </div>
        <div class="form-group">
          <label class="form-label" for="f-dob">Date of birth</label>
          <input class="form-input" id="f-dob" readonly aria-readonly="true" type="date" autocomplete="off" max="{{ now()->toDateString() }}" required>
          <p class="field-error" id="f-dob-error" hidden></p>
        </div>

        <div class="form-group">
          <label class="form-label" for="f-purpose">Purpose <span class="req">(required)</span></label>
          <input class="form-input" id="f-purpose" required maxlength="255" enterkeyhint="next" aria-describedby="f-purpose-help">
          <p class="field-error" id="f-purpose-error" hidden></p>
          <p class="form-note" id="f-purpose-help">Saan gagamitin ang dokumento? Halimbawa: trabaho, scholarship, o bank requirement.</p>
        </div>
        <div id="business-field" style="display:none;" class="form-group">
          <label class="form-label" for="f-business">Business name <span class="req">(required)</span></label>
          <input class="form-input" id="f-business" maxlength="255" autocomplete="off" enterkeyhint="done">
          <p class="field-error" id="f-business-error" hidden></p>
        </div>

        <p class="muted small">Gagamitin lang ang impormasyong ito para sa pagproseso ng request, alinsunod sa RA 10173.</p>
        <div class="btn-row btn-row-split">
          <button type="button" class="btn btn-outline" onclick="goBack('screen-doctype')">Bumalik</button>
          <button type="submit" class="btn btn-green">Next</button>
        </div>
      </form>
    </div>
  </div>

  <div class="screen" id="screen-attachment">
    @include('portal.partials.request-steps', ['current' => 4, 'title' => 'Mag-upload ng valid ID (optional)'])
    <div class="card">
      <div class="card-header">
        <h2>Mag-upload ng valid ID <span class="muted">(optional)</span></h2>
        <p>Hindi required, pero makakatulong ito para mas mabilis ma-verify ng staff ang request.</p>
      </div>
      <div class="card-body">
        <ul class="plain-list" style="margin-bottom:18px;">
          <li>JPG, PNG, o WebP lang, hanggang 5 MB.</li>
          <li>Kunan nang buo ang ID. Dapat malinaw ang pangalan at litrato, walang silaw o blur.</li>
          <li>Dalhin pa rin ang original na valid ID pagkuha ng dokumento sa Barangay Hall.</li>
        </ul>
        <label class="sr-only" for="f-attachment">Litrato ng valid ID</label>
        <input type="file" id="f-attachment" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" onchange="previewAttachment(this)">
        <button type="button" id="att-dropzone" class="upload-box" aria-describedby="attachment-help" onclick="document.getElementById('f-attachment').click()" ondragover="event.preventDefault();this.classList.add('is-dragover')" ondragleave="this.classList.remove('is-dragover')" ondrop="handleAttachmentDrop(event)">
          <strong>Pumili ng litrato ng ID</strong>
          <span id="attachment-help">O kumuha gamit ang camera ng phone. JPG, PNG, o WebP, hanggang 5 MB.</span>
        </button>
        <div id="att-preview" class="attachment-preview" style="display:none;">
          <div class="attachment-preview-inner">
            <img id="att-preview-img" alt="Preview ng in-upload na ID">
            <div>
              <p class="attachment-status" role="status">Naka-attach na ang litrato</p>
              <p id="att-preview-name"></p>
              <button type="button" class="btn btn-outline btn-small" onclick="clearAttachment()">Alisin ang litrato</button>
            </div>
          </div>
        </div>
        <div class="btn-row btn-row-split">
          <button type="button" class="btn btn-outline" onclick="goBack('screen-form')">Bumalik</button>
          <button type="button" class="btn btn-green" onclick="goToReview()">Next</button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-review">
    @include('portal.partials.request-steps', ['current' => 5, 'title' => 'I-review at i-submit'])
    <div class="card">
      <div class="card-header">
        <h2>I-review ang request</h2>
        <p>I-check kung tama ang lahat. Hindi na ito mae-edit online pagka-submit.</p>
      </div>
      <div class="card-body">
        <div id="review-summary"></div>
        <p class="small" style="margin-top:12px;"><button type="button" class="btn-link" onclick="goBack('screen-doctype')">Palitan ang dokumento</button> &middot; <button type="button" class="btn-link" onclick="goBack('screen-form')">Palitan ang purpose</button></p>
        <div class="btn-row btn-row-split">
          <button type="button" class="btn btn-outline" onclick="goBack('screen-attachment')">Bumalik</button>
          <button type="button" class="btn btn-green" id="submit-request-button" onclick="submitRequest()">I-submit ang request</button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-confirm">
    <div class="card">
      <div class="card-header confirm-head">
        <span class="confirm-mark" aria-hidden="true"></span>
        <div>
          <h2>Na-submit na ang request ninyo</h2>
          <p>Nire-review na ito ng barangay staff.</p>
        </div>
      </div>
      <div class="card-body">
        <div class="confirm-ticket">
          <div>
            <p class="confirm-ticket-label">Reference number</p>
            <p class="confirm-code" id="conf-code"></p>
          </div>
          <button class="btn btn-outline btn-small copy-code-button" type="button" onclick="copyReferenceCode()">Kopyahin ang code</button>
        </div>
        <p>I-screenshot o isulat ang reference number. Kailangan ito pagkuha ng dokumento.</p>

        <h3 class="fieldset-title" style="margin-top:24px;">Buod ng request</h3>
        <div id="conf-summary"></div>

        <h3 class="fieldset-title" style="margin-top:24px;">Susunod na gagawin</h3>
        <ol class="confirm-steps">
          <li>Bantayan ang status sa My requests.</li>
          <li>Kapag <strong>Ready for release</strong> na, pumunta sa Barangay Hall ng Anabu I-G. Valid ang online request nang 30 araw mula sa pag-submit.</li>
          <li>Dalhin ang 1 valid government ID at ang reference number: <strong id="conf-code-mini"></strong></li>
          <li>Bayaran sa Barangay Hall ang fee, kung mayroon.</li>
        </ol>
        <div class="btn-row">
          <a class="btn btn-green" href="{{ route('portal.account') }}">Pumunta sa My requests</a>
          <button type="button" class="btn btn-outline" onclick="newRequest()">Mag-request ng iba pang dokumento</button>
        </div>
      </div>
    </div>
  </div>

  <div class="screen" id="screen-status">
    <div class="card">
      <div class="card-header">
        <h2>I-check ang status</h2>
        <p>Ilagay ang reference number ng request. Makikita rin ang lahat ng request ninyo sa <a href="{{ route('portal.account') }}">My requests</a>.</p>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label" for="status-code">Reference number</label>
          <div class="status-search-row">
            <input class="form-input" id="status-code" maxlength="32" autocapitalize="characters" autocomplete="off" spellcheck="false" enterkeyhint="search" placeholder="REQ-2026-XXXXXX" onkeydown="if(event.key==='Enter')checkStatus()">
            <button type="button" class="btn btn-green" onclick="checkStatus()">Hanapin</button>
          </div>
        </div>
        <div id="status-result" role="status" aria-live="polite" style="display:none;"></div>
        <div class="btn-row">
          <button type="button" class="btn btn-outline" onclick="showScreen('screen-terms')">Bumalik sa simula</button>
        </div>
      </div>
    </div>
  </div>

  </section>
</div>
@endsection

@push('scripts')
<script>
const API = '';
const DOC_TYPES = {{ Illuminate\Support\Js::from(collect($services)->mapWithKeys(fn ($service) => [$service->portalCode() => ['label' => $service->value, 'fee' => $service->feeLabel()]])) }};
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
        notice.textContent = 'Nabasa na ang terms. Puwede nang i-check ang kahon.';
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
@endpush
