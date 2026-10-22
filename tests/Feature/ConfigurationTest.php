<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_legacy_environment_conversion_preserves_secrets_without_printing_them(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'is-tools-source-');
        $target = $source.'-converted';
        try {
            file_put_contents($source, "DATABASE_HOST_1=localhost\nDATABASE_NAME_1=is_tools\nDATABASE_USER_1=legacy_user\nDATABASE_PASSWORD_1='p\$a\"ss\\word'\nLDAP_SERVER=ldap://dc.example.test\nDOMAIN=example\nMAIL_USERNAME=service\nMAIL_PASSWORD='private-password'\nSUPPORT_EMAIL=support@example.test\n");
            $this->artisan('is-tools:configure-legacy', ['source' => $source, '--output' => $target])->assertSuccessful();
            $parsed = Dotenv::parse(file_get_contents($target));
            $this->assertSame('p$a"ss\\word', $parsed['DB_PASSWORD']);
            $this->assertSame('is_tools', $parsed['DB_DATABASE']);
            $this->assertSame('host.docker.internal', $parsed['DB_HOST']);
            $this->assertSame('ldap://dc.example.test', $parsed['LDAP_HOST']);
            $this->assertSame('service', $parsed['LDAP_SYNC_BIND_USERNAME']);
            $this->assertSame('private-password', $parsed['LDAP_SYNC_BIND_PASSWORD']);
            $this->assertSame('true', $parsed['LDAP_START_TLS']);
            $this->artisan('is-tools:configure-legacy', ['source' => $source, '--output' => $target])->assertFailed();
        } finally {
            unlink($source);
            if (is_file($target)) {
                unlink($target);
            }
        }
    }
}
