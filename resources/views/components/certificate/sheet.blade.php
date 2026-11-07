@props(['document', 'label', 'withQr' => true])
{{--
    One A4 certificate page: the letterhead and footer every 2026 template shares, with the certificate's own body in
    the slot. The verification QR sits in the empty band between the signature area and the City of Imus mark so it
    never covers wording, signatures or logos.
--}}
<section {{ $attributes->class(['sheet', 'preview' => $document->isPreview]) }} aria-label="{{ $label }}">
    <img class="imus-seal" src="{{ asset('images/certificates/imus-seal.png') }}" alt="City of Imus seal">
    <img class="anabu-seal" src="{{ asset('images/certificates/anabu-source-seal.png') }}" alt="Barangay Anabu I-G seal">
    <img class="national-seal" src="{{ asset('images/certificates/bagong-pilipinas.png') }}" alt="Bagong Pilipinas">
    <p class="letterhead-text">
        <span>Republic of the Philippines</span>
        <span>Province of Cavite</span>
        <span>City of Imus</span>
        <strong class="office">OFFICE OF THE SANGGUNIANG BARANGAY</strong>
        <strong class="barangay">BARANGAY ANABU I-G</strong>
    </p>
    <span class="letterhead-rule" aria-hidden="true"></span>
    {{ $outside ?? '' }}
    <div class="sheet-body" data-sheet-body>
        {{ $slot }}
    </div>
    @if ($withQr && ! $document->isPreview && $document->qrUrl())
        <figure class="verification" data-keep-clear>
            <img src="{{ $document->qrUrl() }}" alt="Certificate authenticity QR code">
            <figcaption>Scan QR code to verify</figcaption>
        </figure>
    @endif
    <img class="imus-footer" data-keep-clear src="{{ asset('images/certificates/imus-footer.png') }}" alt="City of Imus footer logo">
</section>
