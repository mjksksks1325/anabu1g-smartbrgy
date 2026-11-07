{{--
    The Barangay resident ID card (85.6 x 54 mm, front only). As a preview ($isPreview) it shows what issuing the
    request would print, with the photo on the linked resident record: no ID number or QR code yet, a "not valid"
    watermark, and printing blocked. Nothing is saved.
--}}
@php($isPreview = $isPreview ?? false)
@php($photoUrl = $isPreview ? $document->photoUrl() : ($certificate->photo_path ? route('staff.issued-certificates.photo', $certificate) : null))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $isPreview ? 'Barangay ID preview (not issued)' : 'Barangay Resident ID' }}</title>
    <style>
        @page { size: 85.6mm 54mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #111; font-family: 'Times New Roman', serif; }
        .id-card { position: relative; width: 85.6mm; height: 54mm; margin: 24px auto; padding: 2mm; overflow: hidden; background: #fff; border: .45mm solid #163650; border-radius: 1.5mm; }
        .id-card::before { position: absolute; top: 0; left: 0; width: 100%; height: 1.2mm; background: #163650; content: ''; }
        .id-header { display: flex; height: 11.3mm; align-items: center; justify-content: center; gap: 1.2mm; padding: 1mm 1mm .8mm; border-bottom: .25mm solid #b48a35; text-align: center; }
        .seal { flex: 0 0 auto; width: 8mm; height: 8mm; object-fit: contain; }
        .header-text { flex: 1; min-width: 0; font-size: 4.4pt; line-height: 1.1; }
        .header-text .office { display: block; margin-top: .5mm; font-size: 4pt; font-weight: bold; }
        .barangay-name { display: block; font-size: 5pt; font-weight: bold; }
        .id-title { display: block; margin-top: .45mm; color: #163650; font-size: 5.2pt; font-weight: bold; letter-spacing: .1pt; }
        .id-body { display: flex; gap: 2mm; padding: 1.6mm .7mm 0; }
        .photo { flex: 0 0 auto; width: 18mm; height: 21mm; border: .25mm solid #163650; object-fit: cover; }
        .photo-missing { display: flex; align-items: center; justify-content: center; padding: 1mm; color: #4b5563; font: 4.5pt/1.2 Arial, sans-serif; text-align: center; }
        .details { min-width: 0; flex: 1; padding-top: .2mm; font-size: 5.5pt; line-height: 1.18; }
        .name { display: block; max-height: 7mm; margin-bottom: 1mm; overflow: hidden; color: #163650; font-size: 8pt; font-weight: bold; line-height: 1.05; text-transform: uppercase; overflow-wrap: anywhere; }
        .field { display: flex; gap: 1mm; margin-top: .65mm; }
        .field strong { flex: 0 0 auto; font-weight: bold; }
        .field span { min-width: 0; overflow-wrap: anywhere; }
        .id-footer { position: absolute; right: 2mm; bottom: 1.6mm; left: 2.7mm; display: flex; height: 12mm; align-items: flex-end; justify-content: space-between; }
        .verification { align-self: flex-end; max-width: 43mm; padding-bottom: 1mm; font: 4.5pt/1.2 Arial, sans-serif; }
        .qr { display: block; width: 12mm; height: 12mm; margin-bottom: .5mm; }
        .imus-footer { width: 8.5mm; height: 10.5mm; object-fit: contain; }
        .qr-pending { display: flex; width: 12mm; height: 12mm; margin-bottom: .5mm; align-items: center; justify-content: center; padding: .5mm; border: .25mm dashed #6b7280; color: #4b5563; font: 3.6pt/1.15 Arial, sans-serif; text-align: center; }
        .id-card.preview::after { position: absolute; left: 50%; top: 50%; z-index: 3; content: 'PREVIEW · NOT VALID'; transform: translate(-50%, -50%) rotate(-20deg); color: rgba(185, 28, 28, .35); font: bold 13pt/1 Arial, Helvetica, sans-serif; white-space: nowrap; pointer-events: none; }
        .preview-note { width: calc(100% - 32px); max-width: 520px; margin: 0 auto 12px; padding: 10px 14px; border: 1px solid #1d4ed8; border-radius: 6px; background: #eff6ff; color: #1e3a8a; font: 14px/1.45 system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        .certificate-navigation { margin-bottom: 16px; }
        .print-button { display: block; margin: 0 auto 18px; padding: 9px 18px; border: 0; color: white; cursor: pointer; }
        @media screen and (max-width: 430px) {
            .certificate-navigation { align-items: flex-start; }
            .id-card { margin-top: 16px; margin-bottom: 16px; }
        }
        @media print {
            body { background: #fff; }
            .certificate-navigation, .print-button { display: none; }
            .id-card { margin: 0; border-radius: 0; box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body[data-printable="false"] .id-card, body[data-printable="false"] .preview-note { display: none; }
            body[data-printable="false"]::after { display: block; padding: 4mm; content: 'Preview only. This Barangay ID has not been issued and cannot be printed from here.'; font: 7pt/1.3 Arial, Helvetica, sans-serif; }
            body[data-printable="false"][data-name-unfit]::after { content: 'This Barangay ID cannot be printed: the full name does not fit the approved name area.'; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/certificates.css') }}">
</head>
<body data-printable="{{ $isPreview ? 'false' : 'true' }}">
<nav class="certificate-navigation" aria-label="Certificate navigation">
    <a href="{{ route('staff.document-requests.index') }}">Back to certificates</a>
    @if ($isPreview)
        <strong>Preview only: this ID has not been issued and cannot be printed from here.</strong>
    @else
        <span>Barangay Anabu I-G / Print preview</span>
    @endif
</nav>

@if ($isPreview)
    @if ($photoUrl === null)
        <p class="preview-note" role="note">
            @if ($linkedToResident ?? false)
                No photo on file: the linked resident record has no photo, so the card would be issued with "No photo on file" in the photo frame. To include a photo, add it to the resident record in Resident Records, then preview again.
            @else
                No photo on file: this request is not linked to a resident record, so the card shows the requested name and would be issued with "No photo on file" in the photo frame.
            @endif
        </p>
    @endif
@else
    <button class="print-button" type="button" data-print-id onclick="window.print()">Print Barangay ID</button>
@endif
<p class="preview-note" data-name-fit-warning hidden role="alert">{{ __("This name does not fit the Barangay ID's approved name area, even at the smallest type allowed (7 pt), so the ID cannot be printed. Nothing has been shortened, and the name should not be shortened to make it fit. Ask a supervisor to review it: the barangay has to approve a layout for names this long.") }}</p>

<main @class(['id-card', 'preview' => $isPreview]) aria-label="Barangay resident identification card">
    <header class="id-header">
        <img src="{{ asset('images/certificates/imus-seal.png') }}" class="seal" alt="City of Imus seal">
        <div class="header-text">
            <div>Republic of the Philippines<br>Province of Cavite<br>City of Imus</div>
            <strong class="office">OFFICE OF THE SANGGUNIANG BARANGAY</strong>
            <strong class="barangay-name">BARANGAY ANABU I-G</strong>
            <strong class="id-title">BARANGAY RESIDENT IDENTIFICATION CARD</strong>
        </div>
        <img src="{{ asset('images/certificates/anabu-source-seal.png') }}" class="seal" alt="Barangay Anabu I-G seal">
        <img src="{{ asset('images/certificates/bagong-pilipinas.png') }}" class="seal" alt="Bagong Pilipinas">
    </header>

    <section class="id-body" aria-label="Resident identity details">
        @if ($photoUrl !== null)
            <img class="photo" src="{{ $photoUrl }}" alt="{{ $isPreview ? 'Resident photo on record' : 'Resident photo at issuance' }}">
        @else
            <div class="photo photo-missing">No photo on file</div>
        @endif
        <div class="details">
            <strong class="name">{{ $certificate->resident_name }}</strong>
            <div class="field"><strong>ID No.:</strong><span>{{ $isPreview ? 'Assigned at issuance' : $certificate->certificate_number }}</span></div>
            <div class="field"><strong>Barangay:</strong><span>Anabu I-G, Imus, Cavite</span></div>
            <div class="field"><strong>Date Issued:</strong><span>{{ $certificate->issued_at?->format('M d, Y') ?? '' }}</span></div>
            <div class="field"><strong>Valid Until:</strong><span>{{ $certificate->validUntil()?->format('M d, Y') ?? '' }}</span></div>
        </div>
    </section>

    <footer class="id-footer">
        <div class="verification">
            @if ($isPreview)
                <div class="qr-pending">QR code added at issuance</div>
                <div>Preview only: not verifiable</div>
            @else
                @if ($document->qrUrl())
                    <img class="qr" src="{{ $document->qrUrl() }}" alt="Certificate authenticity QR code">
                @endif
                <div>Scan QR code to verify</div>
            @endif
        </div>
        <img class="imus-footer" src="{{ asset('images/certificates/imus-footer.png') }}" alt="City of Imus footer logo">
    </footer>
</main>
<script>
    /*
     * Name fit. The name area holds two lines; a longer name is never cut off. Like the paper certificates' fit check,
     * the type steps down from the template's 8 pt to 7.5 pt and then 7 pt, never smaller; if the full name still does
     * not fit, the card is not printable and staff are told why.
     */
    (document.fonts ? document.fonts.ready : Promise.resolve()).then(() => {
        const name = document.querySelector('.id-card .name');
        if (!name) return;
        const fits = () => name.scrollHeight <= name.clientHeight + 1 && name.scrollWidth <= name.clientWidth + 1;
        for (const size of ['7.5pt', '7pt']) {
            if (fits()) break;
            name.style.fontSize = size;
            name.dataset.fitAdjusted = size;
        }
        if (! fits()) {
            name.style.fontSize = '';
            document.body.dataset.printable = 'false';
            document.body.dataset.nameUnfit = 'true';
            document.querySelector('[data-name-fit-warning]').hidden = false;
            document.querySelector('[data-print-id]')?.setAttribute('disabled', '');
        }
    });
</script>
</body>
</html>
