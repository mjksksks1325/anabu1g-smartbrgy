{{-- The FULL / ADDRESS ... EXPIRATION DATE list of the Indigency and Bona Fide Resident/Voter templates. --}}
<x-certificate.details :rows="[
    ['FULL', $document->fullName(), false],
    ['ADDRESS', $document->address(), false],
    ['DATE OF BIRTH', $document->birthDate()?->format('F d, Y'), false],
    ['GENDER', $document->detail('gender'), false],
    ['CIVIL STATUS', $document->detail('civil_status'), false],
    ['NATIONALITY', $document->detail('nationality'), false],
    ['PURPOSE', $document->purpose(), true],
    ['DATE ISSUE', $document->issuedAt()?->format('F d, Y'), true],
    ['EXPIRATION DATE', $document->validUntil()?->format('F d, Y'), false],
]" />
