<?php

return [
    'mail' => [
        'hello' => 'Olá!',
        'whoops' => 'Ops!',
        'regards' => 'Atenciosamente,',
        'rights_reserved' => 'Todos os direitos reservados.',
        'subcopy' => 'Se você tiver problemas para clicar no botão ":actionText", copie e cole o URL abaixo no seu navegador:',
    ],
    'job_digest' => [
        'subject' => '{1} :count nova vaga compatível|[2,*] :count novas vagas compatíveis',
        'greeting' => 'Olá :name,',
        'intro' => '{1} Encontramos :count vaga que combina com o seu perfil:|[2,*] Encontramos :count vagas que combinam com o seu perfil:',
        'table_header_position' => 'Vaga',
        'table_header_company' => 'Empresa',
        'table_header_score' => 'Score',
        'table_header_reason' => 'Motivo',
        'action' => 'Ver todas as correspondências',
        'footer' => 'Você pode gerenciar alertas de vagas e a pontuação mínima no Apleqz.',
    ],
    'reminder' => [
        'subject' => 'Lembrete: :position na :company',
        'greeting' => 'Olá :name,',
        'intro' => 'Este é um lembrete sobre sua candidatura para :position na :company.',
        'reason_label' => 'Motivo: :reason',
        'action' => 'Abrir candidatura',
        'footer' => 'Você pode gerenciar ou desativar lembretes na página da candidatura no Apleqz.',
        'reasons' => [
            'check_in' => 'Acompanhar esta candidatura',
            'moment' => 'Evento próximo nesta candidatura',
            'custom' => 'Lembrete personalizado',
        ],
    ],
];
