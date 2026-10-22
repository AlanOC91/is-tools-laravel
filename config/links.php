<?php

return [
    'apps' => [
        ['IS Tools', 'Hospital requests and teaching attendance', 'https://apps:3071/', 'panels-top-left'],
        ['Vaultwarden', 'Hospital password manager', 'https://vault.apps/', 'lock-keyhole'],
        ['Patient Tracker · Ground Floor', 'Ground floor patient tracking', 'https://apps/patient-tracker-ground-floor/', 'heart-pulse'],
        ['Patient Tracker · First Floor', 'First floor patient tracking', 'https://apps/patient-tracker-first-floor/', 'heart-pulse'],
        ['Bed Tracker', 'Hospital bed tracking', 'https://apps:3050/', 'bed-double'],
        ['Patient Document Search', 'Find patient documents', 'https://apps/patient-document-search/', 'file-search'],
        ['Pharmacy Whiteboard', 'Pharmacy department board', 'https://apps/pharmacy-whiteboard/', 'pill'],
        ['SJL Digital Phonebook', 'Hospital staff directory', 'https://apps:3060/', 'contact'],
        ['Staff SMS', 'Major emergency SMS tool', 'https://apps/staff-sms/', 'message-square'],
        ['E-Discharges', 'UHL discharge summaries', 'http://uhlaedbrpt/DischargeSummary/', 'file-heart'],
        ['PDF to Image', 'Convert PDF documents into images', 'https://apps/pdf-to-image/', 'images'],
    ],
    'office365' => [
        ['Admin consent requests', 'Approve application access requests', 'https://entra.microsoft.com/#view/Microsoft_AAD_IAM/StartboardApplicationsMenuBlade/~/AccessRequests?Microsoft_AAD_IAM_legacyAADRedirect=true', 'key-round'],
        ['Email quarantine', 'Review quarantined messages', 'https://security.microsoft.com/threatreview?tid=ef56cd66-0a7d-4ce9-af47-bf8a0da9b9db', 'mail-warning'],
        ['Email trace', 'Trace email delivery in Exchange', 'https://admin.exchange.microsoft.com/#/messagetrace', 'route'],
        ['Intune devices', 'Manage hospital devices', 'https://intune.microsoft.com/?ref=AdminCenter#view/Microsoft_Intune_DeviceSettings/DevicesMenu/~/overview', 'monitor-smartphone'],
        ['App registrations', 'Manage registered applications', 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade/quickStartType~/null/sourceType/Microsoft_AAD_IAM?Microsoft_AAD_IAM_legacyAADRedirect=true', 'app-window'],
    ],
];
