{{--
    Shared page for the official certificates. Every sheet is one A4 page laid out in millimetres from the barangay's
    2026 Word templates (public/certificates_format/): 25.4 mm side margins, Cambria 12 pt at a 4.95 mm line pitch,
    the three-seal letterhead and the City of Imus footer mark. Each certificate keeps its own body and wording.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $certificate->certificate_type }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; background: #d9dee5; color: #000; }
        .certificate-controls { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px 16px; padding: 10px 16px; background: #fff; border-bottom: 1px solid #c4cbd4; font: 14px/1.4 system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        .certificate-controls a { color: #1d4ed8; }
        .certificate-controls button { padding: 7px 16px; border: 1px solid #1f6f43; border-radius: 6px; background: #1f6f43; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .certificate-controls button:disabled { border-color: #9aa3ad; background: #9aa3ad; cursor: not-allowed; }
        .certificate-controls .print-note { color: #4b5563; font-size: 13px; }
        .certificate-alert { max-width: 210mm; margin: 12px auto 0; padding: 10px 14px; border: 1px solid #b45309; border-radius: 6px; background: #fff7ed; color: #7c2d12; font: 14px/1.45 system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        .certificate-alert[hidden] { display: none; }
        .certificate-note { max-width: 210mm; margin: 12px auto 0; padding: 10px 14px; border: 1px solid #1d4ed8; border-radius: 6px; background: #eff6ff; color: #1e3a8a; font: 14px/1.45 system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        .sheets { padding: 16px 0 24px; }

        .sheet { position: relative; width: 210mm; height: 297mm; margin: 0 auto 16px; overflow: hidden; background: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, .18); color: #000; font-family: Cambria, 'Cambria Math', Georgia, 'Times New Roman', serif; font-size: 12pt; line-height: 4.95mm; }
        .sheet img { position: absolute; display: block; }
        .sheet .imus-seal { left: 9.5mm; top: 14.2mm; width: 23.4mm; height: 24.3mm; }
        .sheet .anabu-seal { left: 36.2mm; top: 10.8mm; width: 29.4mm; height: 27.8mm; }
        .sheet .national-seal { left: 163.5mm; top: 14.3mm; width: 26.8mm; height: 25.7mm; }
        .sheet p.letterhead-text { position: absolute; left: 25.4mm; top: 18mm; width: 159.2mm; margin: 0; text-align: center; text-indent: 0; }
        .sheet .letterhead-text span, .sheet .letterhead-text strong { display: block; }
        .sheet .letterhead-text .office { margin-top: 5.7mm; }
        .sheet .letterhead-text .barangay { margin-top: .75mm; }
        .sheet .letterhead-rule { position: absolute; left: 32.2mm; top: 52.45mm; width: 161.2mm; height: 1mm; background: #000; }
        .sheet .imus-footer { left: 169.9mm; top: 250.4mm; width: 29.4mm; height: 37.5mm; object-fit: contain; }
        .sheet .verification { position: absolute; left: 148.5mm; top: 265mm; width: 21mm; margin: 0; text-align: center; font: 6.5pt/1.2 Arial, Helvetica, sans-serif; }
        .sheet .verification img { position: static; width: 17mm; height: 17mm; margin: 0 auto .8mm; }

        .sheet-body { position: absolute; left: 25.4mm; right: 24mm; top: 58.2mm; bottom: 15mm; }
        .sheet h1 { margin: 0 0 10mm; font-size: 18pt; line-height: 7.3mm; font-weight: bold; text-align: center; }
        .sheet h1.spaced { margin-top: 4.6mm; padding-left: 12.7mm; letter-spacing: .24em; }
        .sheet p { margin: 0 0 4.95mm; text-align: justify; text-indent: 12.7mm; }
        .sheet p.salutation { margin-bottom: 4.95mm; text-indent: 0; text-align: left; }
        .sheet p.issued { text-align: left; }
        .sheet .fill { font-weight: bold; text-decoration: underline; text-underline-offset: .6mm; }
        .sheet .upper { text-transform: uppercase; }
        .sheet sup { font-size: 7pt; line-height: 0; }
        .sheet .details { width: 100%; margin: 0 0 4.95mm; border-collapse: collapse; table-layout: fixed; }
        .sheet .details th { width: 38.1mm; padding: 0; font-weight: normal; text-align: left; vertical-align: top; white-space: nowrap; }
        .sheet .details td { padding: 0; font-weight: bold; text-transform: uppercase; vertical-align: top; overflow-wrap: anywhere; }
        .sheet .details tr.group-start > * { padding-top: 4.95mm; }
        .sheet .photo { width: 26.7mm; height: 28.4mm; object-fit: cover; }
        .sheet .thumb-mark { width: 31.8mm; margin-top: 3.7mm; text-align: center; }
        .sheet .thumb-box { width: 25.4mm; height: 25.4mm; border: .75pt solid #000; border-radius: 4.2mm; }
        .sheet .thumb-label { display: block; width: 25.4mm; margin-top: 3.2mm; font: italic 8pt/1 'Times New Roman', Times, serif; }
        .sheet .resident-signature { margin: 12.7mm 0 0; text-indent: 0; text-align: left; }
        .sheet .resident-signature strong { display: block; text-decoration: underline; text-underline-offset: .6mm; text-transform: uppercase; }
        .sheet .resident-signature span { display: block; font: bold italic 10pt/4mm 'Times New Roman', Times, serif; }
        .sheet .clearance-photo, .clearance .photo { left: 159.6mm; top: 56.4mm; }
        .sheet .official-signature { width: 75mm; margin: 17mm 0 0; text-align: center; text-indent: 0; }
        .sheet .official-signature strong { display: block; padding-bottom: .6mm; border-bottom: .75pt solid #000; text-transform: uppercase; }
        .sheet .official-signature span { display: block; }

        /* Fit step 1: halve the blank-line gaps below the heading area (title and salutation stay clear of the photo). */
        .sheet.fit-tight p:not(.salutation), .sheet.fit-tight .details, .sheet.fit-tight ol, .sheet.fit-tight li { margin-bottom: 2.4mm; }
        .sheet.fit-tight .details tr.group-start > * { padding-top: 2.4mm; }
        .sheet.fit-tight .thumb-mark { margin-top: 2.4mm; }
        .sheet.fit-tight .resident-signature { margin-top: 7mm; }
        .sheet.fit-tight .official-signature { margin-top: 10mm; }
        .sheet.preview::after { position: absolute; left: 50%; top: 50%; z-index: 3; content: 'PREVIEW · NOT YET ISSUED · NOT VALID'; transform: translate(-50%, -50%) rotate(-35deg); color: rgba(185, 28, 28, .28); font: bold 26pt/1 Arial, Helvetica, sans-serif; white-space: nowrap; pointer-events: none; }

        @media print {
            body { background: #fff; }
            .certificate-controls, .certificate-alert, .certificate-note { display: none !important; }
            .sheets { padding: 0; }
            .sheet { margin: 0; box-shadow: none; break-after: page; }
            .sheet:last-child { break-after: auto; }
            body[data-printable="false"] .sheets { display: none; }
            body[data-printable="false"]::after { display: block; padding: 30mm 25mm; content: 'This page cannot be printed. Open the issued certificate to print it.'; font: 14pt/1.4 Arial, Helvetica, sans-serif; }
        }
        @yield('styles')
    </style>
</head>
<body data-printable="{{ ($isPreview ?? false) ? 'false' : 'true' }}">
<nav class="certificate-controls" aria-label="Certificate actions">
    <a href="{{ $backUrl ?? route('staff.document-requests.index') }}">{{ $backLabel ?? 'Back to certificates' }}</a>
    @if ($isPreview ?? false)
        <strong>Preview only: this certificate has not been issued and cannot be printed from here.</strong>
    @else
        <button type="button" data-print-certificate onclick="window.print()">Print certificate</button>
        <span class="print-note">Print on A4 paper at 100% scale, with headers and footers turned off.</span>
    @endif
</nav>
<p class="certificate-alert" data-fit-warning hidden role="alert">{{ __("These details do not fit this certificate's approved one-page layout, even with tighter spacing and the smallest type allowed (11 pt), so it cannot be printed. Nothing has been shortened, and no detail should be shortened to make it fit. Ask a supervisor to review it: the barangay has to approve a layout for details this long.") }}</p>
<p class="certificate-note" data-fit-adjusted hidden role="status"></p>
@yield('notice')
<main class="sheets">
    @yield('sheets')
</main>
<script>
    /*
     * Fit check. A sheet prints only when its body text stays inside the page margins and no line touches the footer
     * mark, the QR code, the photo or a fixed signature block. When the official layout as designed is too short for
     * long but legitimate details, three predictable steps are tried in order, re-measuring after each:
     *   1. blank-line gaps between paragraphs and detail groups are halved;
     *   2. body text is set at 95.8% of the template's size (11.5 pt on 12 pt templates);
     *   3. body text is set at 91.7% (11 pt on 12 pt templates), never below 11 pt.
     * Titles, labels, captions and the letterhead keep their sizes, and nothing is ever truncated. If the details still
     * do not fit, printing is blocked and staff are told why.
     */
    (document.fonts ? document.fonts.ready : Promise.resolve()).then(() => {
        const MIN_BODY_PX = 11 * 96 / 72;
        const overlaps = (a, b) => a.left < b.right - .5 && b.left < a.right - .5 && a.top < b.bottom - .5 && b.top < a.bottom - .5;
        const inkRects = body => {
            const rects = [...body.querySelectorAll('.thumb-box')].map(box => box.getBoundingClientRect());
            const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
            while (walker.nextNode()) {
                if (walker.currentNode.textContent.trim() === '') continue;
                const range = document.createRange();
                range.selectNodeContents(walker.currentNode);
                rects.push(...range.getClientRects());
            }
            return rects;
        };
        const fits = body => {
            const keepClear = [...body.closest('.sheet').querySelectorAll('[data-keep-clear]')].map(element => element.getBoundingClientRect());
            return body.scrollHeight <= body.clientHeight + 1 && body.scrollWidth <= body.clientWidth + 1
                && ! inkRects(body).some(rect => keepClear.some(area => overlaps(rect, area)));
        };
        const steps = [
            { label: 'tighter spacing', apply: sheet => sheet.classList.add('fit-tight') },
            { label: 'tighter spacing and body text at 95.8% of its size', scale: 11.5 / 12 },
            { label: 'tighter spacing and body text at 91.7% of its size', scale: 11 / 12 },
        ];
        const adjusted = [];
        const unfit = [];

        document.querySelectorAll('[data-sheet-body]').forEach(body => {
            const sheet = body.closest('.sheet');
            const base = getComputedStyle(body);
            const baseSize = parseFloat(base.fontSize);
            const baseLine = parseFloat(base.lineHeight);
            let applied = null;

            for (const step of steps) {
                if (fits(body)) break;
                step.apply?.(sheet);
                if (step.scale) {
                    const size = Math.max(MIN_BODY_PX, baseSize * step.scale);
                    body.style.fontSize = size + 'px';
                    body.style.lineHeight = (baseLine * size / baseSize) + 'px';
                }
                applied = step;
            }

            if (! fits(body)) {
                sheet.dataset.overflowing = 'true';
                unfit.push(sheet);
            } else if (applied !== null) {
                sheet.dataset.fitAdjusted = applied.label;
                adjusted.push(sheet.getAttribute('aria-label') + ': ' + applied.label);
            }
        });

        if (adjusted.length > 0) {
            const note = document.querySelector('[data-fit-adjusted]');
            note.textContent = 'To fit every detail on its page without shortening anything, this layout uses ' + adjusted.join('; ') + '. Check the printout reads well.';
            note.hidden = false;
        }
        if (unfit.length > 0) {
            document.body.dataset.printable = 'false';
            document.querySelector('[data-fit-warning]').hidden = false;
            document.querySelector('[data-print-certificate]')?.setAttribute('disabled', '');
        }
    });
</script>
</body>
</html>
