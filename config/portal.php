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

    /*
     * Barangay Hall contact details and the public emergency hotlines, shown on the portal home page,
     * Information page and footer. "verified" => false shows a Pending verification tag: set it to true
     * once the barangay confirms the entry. 911, the City of Imus line, the first Imus PNP number and the
     * BFP mobile were already listed from the City of Imus website; the rest were provided for Work Group 5
     * (2026-11-07). Office days were not provided, so only the hours are shown.
     */
    'barangay_hall' => [
        'phone' => ['display' => '(046) 230-2905', 'tel' => '+63462302905'],
        'office_hours' => '8:00 AM – 5:00 PM',
        'verified' => false,
    ],

    'emergency_hotlines' => [
        ['label' => 'National emergency hotline', 'numbers' => [['display' => '911', 'tel' => '911']], 'verified' => true],
        ['label' => 'City of Imus emergency', 'numbers' => [['display' => '(046) 888 9911', 'tel' => '+63468889911']], 'verified' => true],
        ['label' => 'Imus CDRRMO / Ambulance', 'numbers' => [['display' => '0919-069-1703', 'tel' => '+639190691703']], 'verified' => false],
        ['label' => 'Imus PNP', 'numbers' => [['display' => '0998-598-5601', 'tel' => '+639985985601'], ['display' => '0998-598-5602', 'tel' => '+639985985602']], 'verified' => false],
        ['label' => 'Imus BFP (Bureau of Fire Protection)', 'numbers' => [['display' => '(046) 416-3032', 'tel' => '+63464163032'], ['display' => '0915-528-3256', 'tel' => '+639155283256']], 'verified' => false],
        // Trunk lines 8301 to 8307: only the first is linked, since a range is not one number to dial.
        ['label' => 'Ospital ng Imus', 'numbers' => [['display' => '(046) 419-8301', 'tel' => '+63464198301']], 'note' => 'Lines 8301 to 8307', 'verified' => false],
        ['label' => 'Barangay Hall', 'numbers' => [['display' => '(046) 230-2905', 'tel' => '+63462302905']], 'note' => 'Office line, not a 24-hour hotline', 'verified' => false],
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
