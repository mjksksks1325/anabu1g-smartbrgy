{{--
    Two pages, as the barangay issues them under RA 11261: the Barangay Certification ("Brgy. Certificate First time job
    seeker.docx") and the Oath of Undertaking the jobseeker signs ("Brgy.Certificate-First time job seeker- Oath of
    Undertaking 2026.docx"). Both wordings are the templates' own, kept word for word. The certification is valid for
    one year from issuance; the Barangay Secretary named in config/certificates.php witnesses it.
--}}
@extends('admin.certificates.layout')

@section('styles')
    .jobseeker .sheet-body { left: 35.4mm; right: 29.2mm; }
    .jobseeker h1 { margin: 3.7mm 0 0; }
    .jobseeker p.subtitle { margin-bottom: 9.9mm; text-align: center; text-indent: 0; }
    .jobseeker .witness { position: absolute; left: 124.3mm; top: 203.2mm; width: 44mm; margin: 0; text-align: left; text-indent: 0; white-space: nowrap; }
    .jobseeker .witness > * { display: block; }
    .jobseeker .witness .label { font-style: italic; }
    .jobseeker .witness .date-label { padding-left: 13mm; }
    .jobseeker .witness .witnessed { padding-left: 9mm; }
    .jobseeker .witness .gap { margin-top: 4.95mm; }
    .jobseeker .witness .gap-wide { margin-top: 9.9mm; }
    .oath .sheet-body { left: 38.5mm; right: 23.7mm; font-size: 11pt; line-height: 4.57mm; }
    .oath h1 { position: relative; left: -6.7mm; margin: 1.7mm 0 7.6mm; }
    .oath p { margin-bottom: 4.57mm; text-indent: 0; }
    .oath ol { margin: 0 0 4.57mm; padding: 0 0 0 8.9mm; }
    .oath li { margin-bottom: 4.57mm; text-align: justify; }
    .oath li::marker { font: bold 11pt 'Times New Roman', Times, serif; }
    .oath p.signed { padding-left: 8.9mm; }
    .oath .jobseeker-signature { position: absolute; left: 113mm; top: 244.2mm; width: 62mm; margin: 0; text-align: center; text-indent: 0; }
    .oath .jobseeker-signature > * { display: block; }
@endsection

@section('sheets')
    @php($secretary = \App\CertificateDocument::official('barangay_secretary'))
    <x-certificate.sheet :document="$document" label="First Time Jobseeker barangay certification" class="jobseeker">
        <x-slot name="outside">
            <p class="witness" data-keep-clear>
                <span class="fill">{{ $document->issuedAt()?->format('F d, Y') }}</span>
                <span class="label date-label">Date</span>
                <strong class="gap witnessed">Witnessed</strong>
                <span class="fill gap-wide">{{ $secretary['name'] }}</span>
                <span class="label">{{ $secretary['title'] }}</span>
                <span class="fill gap-wide">{{ $document->issuedAt()?->format('F d, Y') }}</span>
                <span class="label date-label">Date</span>
            </p>
        </x-slot>
        <h1>BARANGAY CERTIFICATION</h1>
        <p class="subtitle"><strong><em>(First Time Jobseekers Assistance Act- RA 11261)</em></strong></p>
        <p>This is to certify that Mr./Ms. <span class="fill upper">{{ $document->fullName() }}</span>, a resident of <span class="fill upper">{{ $document->address() }}</span>@unless ($document->addressNamesBarangay()), <strong>Barangay Anabu I-G, City of Imus, Cavite</strong>@endunless for <span class="fill">{{ $document->yearsOfResidency() }}</span> years is a qualified availee of <strong>RA 11261</strong> or the <strong><em>First Time Jobseekers Act of 2019.</em></strong></p>
        <p>I further certify that the holder/ bearer was informed of his/her rights. Including the duties and responsibilities accorder by RA 11261 through the Oath of Undertaking he/she has signed and executed in the presence of our Barangay Official.</p>
        <p>Signed this <x-certificate.day :date="$document->issuedAt()" /> in Barangay Anabu I-G, City of Imus Cavite.</p>
        <p>This Certification is valid only <strong>{{ $document->validUntil()?->format('F d, Y') }}</strong>. (one (1) year from issuance).</p>
    </x-certificate.sheet>

    <x-certificate.sheet :document="$document" label="First Time Jobseeker oath of undertaking" class="oath" :with-qr="false">
        <x-slot name="outside">
            <p class="jobseeker-signature" data-keep-clear><span class="fill upper">{{ $document->fullName() }}</span><span>First Time Jobseeker</span></p>
        </x-slot>
        <h1>OATH OF UNDERTAKING</h1>
        <p>I, <span class="fill upper">{{ $document->fullName() }}</span>, <span class="fill">{{ $document->ageAtIssue() }}</span> years of age, resident of <span class="fill upper">{{ $document->address() }}</span>{{ $document->addressNamesBarangay() ? '' : ', Barangay Anabu I-G, City of Imus, Cavite' }} for <span class="fill">{{ $document->yearsOfResidency() }}</span> years. Availing the benefits of <strong>Republic Act 11261,</strong> otherwise known as the <strong>First Time Jobseekers Act of 2019,</strong> do hereby declare, agree and undertake to abide and be bound by the following;</p>
        <ol>
            <li>That this is the first time that I will Actively look for a job, and therefore requesting that a Barangay Certification be issued in my favor to avail the benefits of the law;</li>
            <li>That I am aware that the benefit and privilege/s under the said law shall be valid only for one (1) year from the date that the Barangay Certification is issued;</li>
            <li>That I can avail the benefits of the law only once;</li>
            <li>That I understand that my personal information shall be included in the Roster/List of First Time Jobseekers and will not be used for any unlawful purpose;</li>
            <li>That I will inform and/or report to the Barangay personally, through text or other means, or through my family/relatives once I get employed; and</li>
            <li>That I am not a beneficiary of the Job Start Program under R.A. No. 10869 and other laws that give similar exemptions for the documents or transactions exempted under R.A. No. 11261</li>
            <li>That if issued the requested Certification, I will not use the same in any fraud, neither falsely nor help and/or assist in the fabrication of the said certification.</li>
            <li>That this undertaking is made solely for the purpose of obtaining a Barangay Certification consistent with the objective of R.A. No. 11261 and not for any other purpose.</li>
            <li>That I consent to the use of my personal information pursuant to the Data Privacy Act and other applicable laws, rules, and regulations.</li>
        </ol>
        <p class="signed">Signed this <x-certificate.day :date="$document->issuedAt()" /> in Barangay Anabu I-G, City of Imus Cavite Province of Cavite</p>
    </x-certificate.sheet>
@endsection
