<?php

$domain = env('LDAP_DOMAIN', env('DOMAIN', 'stjohnshospital'));
$format = env('LDAP_BIND_FORMAT', $domain.'\\{username}');
$dedicated = env('LDAP_SYNC_BIND_USERNAME') !== null || env('LDAP_SYNC_BIND_PASSWORD') !== null;

return [
    'enabled' => env('LDAP_ENABLED', true),
    'host' => env('LDAP_HOST', env('LDAP_SERVER', 'ldap://dc-2.stjohnshospital.ie')),
    'port' => env('LDAP_PORT'), 'start_tls' => env('LDAP_START_TLS', true), 'ca_file' => env('LDAP_TLS_CA_FILE'),
    'base_dn' => env('LDAP_BASE_DN', 'dc=stjohnshospital,dc=ie'),
    'bind_format' => $format, 'user_filter' => env('LDAP_USER_FILTER', '(sAMAccountName={username})'),
    'email_domain' => env('LDAP_EMAIL_DOMAIN', 'stjohnshospital.ie'),
    'search_username' => $dedicated ? env('LDAP_SYNC_BIND_USERNAME') : env('MAIL_USERNAME'),
    'search_password' => $dedicated ? env('LDAP_SYNC_BIND_PASSWORD') : env('MAIL_PASSWORD'),
    'search_bind_format' => env('LDAP_SYNC_BIND_FORMAT', $format),
    'search_filter' => env('LDAP_SYNC_USER_FILTER', '(&(objectCategory=person)(objectClass=user)(sAMAccountName=*))'),
    'page_size' => (int) env('LDAP_SYNC_PAGE_SIZE', 500), 'timeout' => (int) env('LDAP_SYNC_TIMEOUT', 15),
];
