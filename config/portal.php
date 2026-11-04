<?php

return [
    /*
     * Resident-facing wording for each document request status, in the order a request moves
     * through them. "rejected" is a stopping state and is shown apart from the stages.
     * "next" tells the resident what, if anything, they need to do now.
     */
    'request_statuses' => [
        'pending' => ['label' => 'Received', 'note' => 'Natanggap na. Hinihintay pa ang review ng barangay staff.', 'next' => 'Wala pa kayong kailangang gawin. Hintayin ang review ng barangay staff.'],
        'processing' => ['label' => 'Processing', 'note' => 'Pinoproseso na ng barangay staff.', 'next' => 'Wala pa kayong kailangang gawin habang pinoproseso ito.'],
        'approved' => ['label' => 'Approved', 'note' => 'Aprubado na. Hintaying maging Ready for release bago pumunta sa Barangay Hall.', 'next' => 'Hintaying maging Ready for release bago pumunta sa Barangay Hall.'],
        'ready_for_release' => ['label' => 'Ready for release', 'note' => 'Puwede nang kunin sa Barangay Hall.', 'next' => 'Pumunta sa Barangay Hall dala ang original na valid ID at ang reference number. Doon babayaran ang fee, kung mayroon.'],
        'released' => ['label' => 'Released', 'note' => 'Nakuha na ang dokumento.', 'next' => 'Tapos na ang request na ito.'],
        'rejected' => ['label' => 'Not approved', 'note' => 'Hindi naaprubahan ang request.', 'next' => 'Tingnan ang dahilan. Puwedeng mag-submit ng bagong request kapag naayos na ang problema.'],
    ],

    'officials' => array_fill(0, 4, [
        'photo_path' => null,
        'full_name' => 'Barangay Official',
        'position' => 'Position',
        'term' => 'Term of Office',
    ]),

    'resources' => [
        [
            // Verified 2026-11-04: EO No. 6 (2016) and PNA; call or text 8888, or file at 8888.gov.ph.
            'id' => '8888',
            'title' => "8888 Citizens' Complaint Hotline",
            'description' => '8888 Citizens\' Complaint Hotline website para sa mga reklamo at feedback tungkol sa serbisyo ng gobyerno.',
            'url' => 'https://8888.gov.ph/',
            'link_label' => 'Visit 8888 website',
        ],
        [
            'title' => 'Provincial Government of Cavite',
            'description' => 'Provincial government information and resources.',
            'url' => 'https://cavite.gov.ph/',
            'link_label' => 'Visit Cavite website',
        ],
        [
            'title' => 'Medical Assistance',
            'description' => 'Tingnan ang impormasyon tungkol sa medical assistance sa Social Welfare Services ng Cavite.',
            'url' => 'https://cavite.gov.ph/social-welfare-services/',
            'link_label' => 'View medical assistance',
        ],
        [
            'title' => 'Burial Assistance',
            'description' => 'Tingnan ang impormasyon tungkol sa burial assistance sa Social Welfare Services ng Cavite.',
            'url' => 'https://cavite.gov.ph/social-welfare-services/',
            'link_label' => 'View burial assistance',
        ],
    ],
];
