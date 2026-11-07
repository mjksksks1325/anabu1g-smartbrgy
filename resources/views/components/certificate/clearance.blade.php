@props(['document', 'title'])
{{--
    Barangay Clearance and Business Clearance. The barangay has not supplied a Word template for these, so they keep
    their existing wording inside the letterhead, typography and details list of the official templates, signed by the
    Punong Barangay named in config/certificates.php. Identity rows the record does not hold (onsite issuance without a
    linked resident) are left out rather than printed blank.
--}}
@php($captain = \App\CertificateDocument::official('punong_barangay'))
<x-certificate.sheet :document="$document" :label="$title" class="clearance">
    <x-slot name="outside"><x-certificate.photo :document="$document" /></x-slot>
    <h1>{{ $title }}</h1>
    <p class="salutation">To Whom it may concern:</p>
    {{ $slot }}
    @include('admin.certificates.partials.resident-details')
    <p class="issued">Issued this <x-certificate.day :date="$document->issuedAt()" /> at the office of Barangay Anabu I-G, City of Imus, Cavite.</p>
    <p class="official-signature"><strong>{{ $captain['name'] }}</strong><span>{{ $captain['title'] }}</span></p>
</x-certificate.sheet>
