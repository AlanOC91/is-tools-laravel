<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;

class ConfigureLegacy extends Command
{
    protected $signature = 'is-tools:configure-legacy {source : Path to the old .env} {--output=.env.production.local : New private environment file; existing files are never overwritten}';

    protected $description = 'Prepare a production environment using the existing private database, LDAP and mail settings';

    public function handle(): int
    {
        $target = $this->option('output');
        if (file_exists($target)) {
            $this->error('The output file already exists. Choose a new filename.');

            return self::FAILURE;
        }
        $legacy = Dotenv::parse(file_get_contents($this->argument('source')));
        $map = [
            'DB_HOST' => ['DB_HOST', 'DATABASE_HOST_1'], 'DB_PORT' => ['DB_PORT'],
            'DB_DATABASE' => ['DB_DATABASE', 'DATABASE_NAME_1'], 'DB_USERNAME' => ['DB_USERNAME', 'DATABASE_USER_1'],
            'DB_PASSWORD' => ['DB_PASSWORD', 'DATABASE_PASSWORD_1'],
            'LDAP_HOST' => ['LDAP_HOST', 'LDAP_SERVER'], 'LDAP_DOMAIN' => ['LDAP_DOMAIN', 'DOMAIN'],
            'LDAP_BASE_DN' => ['LDAP_BASE_DN'], 'LDAP_PORT' => ['LDAP_PORT'],
            'LDAP_BIND_FORMAT' => ['LDAP_BIND_FORMAT'], 'LDAP_USER_FILTER' => ['LDAP_USER_FILTER'],
            'LDAP_EMAIL_DOMAIN' => ['LDAP_EMAIL_DOMAIN'],
            'LDAP_SYNC_BIND_USERNAME' => ['LDAP_SYNC_BIND_USERNAME', 'MAIL_USERNAME'],
            'LDAP_SYNC_BIND_PASSWORD' => ['LDAP_SYNC_BIND_PASSWORD', 'MAIL_PASSWORD'],
            'LDAP_SYNC_BIND_FORMAT' => ['LDAP_SYNC_BIND_FORMAT', 'LDAP_BIND_FORMAT'],
            'MAIL_HOST' => ['MAIL_HOST', 'MAIL_SERVER'], 'MAIL_PORT' => ['MAIL_PORT'],
            'MAIL_USERNAME' => ['MAIL_USERNAME'], 'MAIL_PASSWORD' => ['MAIL_PASSWORD'],
            'MAIL_FROM_ADDRESS' => ['MAIL_FROM_ADDRESS', 'FROM_EMAIL'],
        ];
        foreach (['APP_NAME', 'COMPANY_NAME', 'SUPPORT_EMAIL', 'SUPPORT_EXTENSION', 'ILAB_EMAIL', 'ILAB_EMAIL_CC', 'ILAB_EMAIL_BCC', 'IGO_EMAIL'] as $key) {
            $map[$key] = [$key];
        }
        $values = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
        foreach ($map as $key => $sources) {
            foreach ($sources as $source) {
                if (array_key_exists($source, $legacy)) {
                    $values[$key] = $legacy[$source];
                    break;
                }
            }
        }
        // Preserve the old all-or-nothing directory-search credential fallback.
        if (array_key_exists('LDAP_SYNC_BIND_USERNAME', $legacy) || array_key_exists('LDAP_SYNC_BIND_PASSWORD', $legacy)) {
            $values['LDAP_SYNC_BIND_USERNAME'] = $legacy['LDAP_SYNC_BIND_USERNAME'] ?? '';
            $values['LDAP_SYNC_BIND_PASSWORD'] = $legacy['LDAP_SYNC_BIND_PASSWORD'] ?? '';
        }
        $values['LDAP_BIND_FORMAT'] ??= ($values['LDAP_DOMAIN'] ?? 'stjohnshospital').'\\{username}';
        $values['LDAP_SYNC_BIND_FORMAT'] ??= $values['LDAP_BIND_FORMAT'];
        if (in_array($values['DB_HOST'] ?? '', ['127.0.0.1', 'localhost'], true)) {
            $values['DB_HOST'] = 'host.docker.internal';
        }
        $contents = file_get_contents(base_path('.env.example'));
        foreach ($values as $key => $value) {
            $quoted = '"'.str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '\\r', '\\n'], $value).'"';
            $contents = preg_replace_callback('/^'.preg_quote($key, '/').'=.*/m', fn () => $key.'='.$quoted, $contents);
        }
        file_put_contents($target, $contents);
        $this->info('Prepared '.$target.'. No credentials were printed.');
        $this->line('Review the database host, LDAP certificate hostname, mail port and recipients before deployment.');
        $this->line('TLS remains enabled; the container CA path and Gotenberg service use the new Docker layout.');

        return self::SUCCESS;
    }
}
