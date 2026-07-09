<?php

return [
    'mail' => [
        'hello' => 'Hello!',
        'whoops' => 'Whoops!',
        'regards' => 'Regards,',
        'rights_reserved' => 'All rights reserved.',
        'subcopy' => 'If you\'re having trouble clicking the ":actionText" button, copy and paste the URL below into your web browser:',
    ],
    'job_digest' => [
        'subject' => '{1} :count new job match|[2,*] :count new job matches',
        'greeting' => 'Hello :name,',
        'intro' => '{1} We found :count job listing that matches your profile:|[2,*] We found :count job listings that match your profile:',
        'table_header_position' => 'Position',
        'table_header_company' => 'Company',
        'table_header_score' => 'Score',
        'table_header_reason' => 'Reason',
        'action' => 'View all matches',
        'footer' => 'You can manage job alerts and your minimum fit score in Apleqz.',
    ],
    'reminder' => [
        'subject' => 'Reminder: :position at :company',
        'greeting' => 'Hello :name,',
        'intro' => 'This is a reminder about your application for :position at :company.',
        'reason_label' => 'Reason: :reason',
        'action' => 'Open application',
        'footer' => 'You can manage or disable reminders on the application page in Apleqz.',
        'reasons' => [
            'check_in' => 'Follow up on this application',
            'moment' => 'Upcoming event on this application',
            'custom' => 'Custom reminder',
        ],
    ],
];
