<?php

return [
    'mail' => [
        'hello' => 'Hallo!',
        'whoops' => 'Hoppla!',
        'regards' => 'Mit freundlichen Grüßen,',
        'rights_reserved' => 'Alle Rechte vorbehalten.',
        'subcopy' => 'Wenn Sie Probleme beim Klicken auf die Schaltfläche „:actionText“ haben, kopieren Sie die folgende URL und fügen Sie sie in Ihren Browser ein:',
    ],
    'job_digest' => [
        'subject' => '{1} :count neue Stellenübereinstimmung|[2,*] :count neue Stellenübereinstimmungen',
        'greeting' => 'Hallo :name,',
        'intro' => '{1} Wir haben :count Stellenanzeige gefunden, die zu Ihrem Profil passt:|[2,*] Wir haben :count Stellenanzeigen gefunden, die zu Ihrem Profil passen:',
        'table_header_position' => 'Stelle',
        'table_header_company' => 'Unternehmen',
        'table_header_score' => 'Score',
        'table_header_reason' => 'Begründung',
        'action' => 'Alle Treffer ansehen',
        'footer' => 'Sie können Job-Benachrichtigungen und Ihren Mindest-Score in Apleqz verwalten.',
    ],
    'reminder' => [
        'subject' => 'Erinnerung: :position bei :company',
        'greeting' => 'Hallo :name,',
        'intro' => 'Dies ist eine Erinnerung zu Ihrer Bewerbung als :position bei :company.',
        'reason_label' => 'Grund: :reason',
        'action' => 'Bewerbung öffnen',
        'footer' => 'Sie können Erinnerungen auf der Bewerbungsseite in Apleqz verwalten oder deaktivieren.',
        'reasons' => [
            'check_in' => 'Diese Bewerbung nachverfolgen',
            'moment' => 'Bevorstehendes Ereignis zu dieser Bewerbung',
            'custom' => 'Individuelle Erinnerung',
        ],
    ],
];
