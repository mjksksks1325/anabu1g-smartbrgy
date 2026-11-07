{{--
    Follows "Senior Citizen -Nonagenarian Letter.docx" word for word, co-signed by the Punong Barangay and the Barangay
    Health Worker (config/certificates.php). The authorization sentence appears only when the resident named a representative.
--}}
@extends('admin.certificates.layout')

@php
    $captain = \App\CertificateDocument::official('punong_barangay');
    $healthWorker = \App\CertificateDocument::official('barangay_health_worker');
    $address = $document->address().($document->addressNamesBarangay() ? '' : ', Anabu I-G, City of Imus, Cavite');
@endphp

@section('styles')
    .nonagenarian .co-signatures { display: grid; grid-template-columns: 101.6mm 1fr; margin-top: 22.6mm; text-indent: 0; text-align: left; font-weight: bold; }
    .nonagenarian .co-signatures p { margin: 0; text-indent: 0; text-align: left; }
    .nonagenarian .co-signatures span { display: block; }
    .nonagenarian .co-signatures .name { text-decoration: underline; text-underline-offset: .6mm; }
@endsection

@section('sheets')
    <x-certificate.sheet :document="$document" label="Nonagenarian Certification" class="nonagenarian">
        <h1 class="spaced" aria-label="Certification">CERTIFICATION</h1>
        <p class="salutation">To Whom it may concern;</p>
        <p>This is to certify <strong class="upper">{{ $document->fullName() }}</strong>, of legal age, Filipino, and a resident of <strong>{{ $address }}</strong>, is personally known to this office as a bona fide resident of the barangay.</p>
        @if (filled($document->extra('representative_name')))
            <p>I hereby authorize my {{ $document->extra('representative_relationship') }}, <strong class="upper">{{ $document->extra('representative_name') }}</strong>, to claim my Nonagenarian benefit on my behalf.</p>
        @endif
        <p>This is further to certify that the above-named individual is an <strong>Nonagenarian (90 years old and above),</strong> having been born on <strong>{{ $document->birthDate()?->format('F j') }}</strong>,<strong>{{ $document->birthDate()?->format('Y') }}</strong> and is therefore qualified to avail of benefits granted to senior citizens in accordance with existing laws, including Republic Act No. 9994.</p>
        <p>This certification is issued upon the request of the concerned party for whatever legal purpose it may serve.</p>
        <p>Issued this <x-certificate.day :date="$document->issuedAt()" /> at the Office of Barangay Anabu I-G, City of Imus, Cavite.</p>
        <div class="co-signatures">
            <p><span class="name">{{ preg_replace_callback('/^(Hon\.\s+)(.+)$/u', fn (array $part): string => $part[1].mb_strtoupper($part[2]), $captain['name']) }}</span><span>{{ $captain['title'] }}</span></p>
            <p><span class="name">{{ mb_strtoupper($healthWorker['name']) }}</span><span>{{ $healthWorker['title'] }}</span></p>
        </div>
    </x-certificate.sheet>
@endsection
