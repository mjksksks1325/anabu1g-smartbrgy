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
    ],
];
