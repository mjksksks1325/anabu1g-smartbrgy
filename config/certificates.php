<?php

return [
    /*
     * Barangay officials named on printed certificates. Both names are taken from the barangay's own 2026 Word
     * templates in public/certificates_format/: the Barangay Secretary witnesses the First Time Jobseeker
     * certification ("Brgy. Certificate First time job seeker.docx") and the Punong Barangay signs the
     * Nonagenarian letter ("Senior Citizen -Nonagenarian Letter.docx"). Update them here when the officials change.
     */
    'officials' => [
        'punong_barangay' => ['name' => 'Hon. Robinson P. Silla', 'title' => 'Punong Barangay'],
        'barangay_secretary' => ['name' => 'Sec. Pamela R. Abas', 'title' => 'Barangay Secretary'],
        // Co-signs the Nonagenarian certification ("Senior Citizen -Nonagenarian Letter.docx").
        'barangay_health_worker' => ['name' => 'Frelyn B. Tiro', 'title' => 'Barangay Health Worker'],
    ],

    /*
     * The staff-only certificates (App\AdditionalCertificateType), by slug. None has an approved fee yet, so none can
     * be issued: staff can complete and preview them only. To allow issuance once the barangay approves the fee and
     * requirements, set the fee in pesos (0 for free) and 'enabled' => true. They are never offered to residents online.
     */
    'additional' => [
        'low-income' => ['fee' => null, 'enabled' => false],
        'guardianship' => ['fee' => null, 'enabled' => false],
        'cohabitation' => ['fee' => null, 'enabled' => false],
        'fact-of-birth' => ['fee' => null, 'enabled' => false],
        'probation' => ['fee' => null, 'enabled' => false],
        'national-id-registration' => ['fee' => null, 'enabled' => false],
        'move-out-4ps' => ['fee' => null, 'enabled' => false],
        'no-objection' => ['fee' => null, 'enabled' => false],
        'nonagenarian' => ['fee' => null, 'enabled' => false],
    ],
];
