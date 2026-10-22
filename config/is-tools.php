<?php

return [
    'company' => env('COMPANY_NAME', "St. John's Hospital"),
    'support_email' => env('SUPPORT_EMAIL'),
    'support_extension' => env('SUPPORT_EXTENSION', '246'),
    'ilab_email' => env('ILAB_EMAIL'), 'ilab_cc' => env('ILAB_EMAIL_CC'), 'ilab_bcc' => env('ILAB_EMAIL_BCC'),
    'igo_email' => env('IGO_EMAIL'),
    'gotenberg_url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
    'documents_path' => env('DOCUMENTS_PATH', base_path('documents')),
    'department_folders_path' => env('DEPARTMENT_FOLDERS_PATH'),
    'policy_url' => env('COMMUNICATIONS_POLICY_URL', 'https://intranet-1/Shared%20Documents/Electronic%20communications%20policy.pdf'),
];
