<?php

namespace Tests\Unit;

use App\Services\DirectoryService as Ldap;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__.'/../fixtures/ldap.php';

final class LdapTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        LdapStub::reset();
        $this->config = Dotenv::parse(file_get_contents(__DIR__.'/../fixtures/directory.env'));
        $this->config['LDAP_SYNC_BIND_PASSWORD'] = 'test-service-password';
    }

    private function entry(string $username = 'alice'): array
    {
        return [
            'displayname' => ['Alice Example'],
            'samaccountname' => [$username],
            'memberof' => ['count' => 1, 0 => 'CN=I.S. Staff,DC=example,DC=ie'],
        ];
    }

    public function test_supplied_config_authenticates_with_exact_password_and_loads_profile(): void
    {
        $entry = $this->entry();
        LdapStub::$pages = [['entries' => [$entry]]];
        $ldap = new Ldap($this->config + ['LDAP_SERVER' => 'old.example.ie', 'DOMAIN' => 'old-domain']);

        self::assertSame($entry, $ldap->authenticate('alice', ' password with spaces '));
        self::assertSame([
            ['connect', 'ldap://dc-1.stjohnshospital.ie:389'],
            ['bind', 'stjohnshospital\\alice', ' password with spaces '],
        ], LdapStub::$events);
        self::assertSame('(sAMAccountName=alice)', LdapStub::$searches[0]['filter']);
        self::assertSame('dc=stjohnshospital,dc=ie', LdapStub::$searches[0]['baseDn']);
        self::assertSame([], LdapStub::$searches[0]['controls']);
        self::assertSame(3, LdapStub::$options[LDAP_OPT_PROTOCOL_VERSION]);
        self::assertSame(0, LdapStub::$options[LDAP_OPT_REFERRALS]);
        self::assertSame(30, LdapStub::$options[LDAP_OPT_NETWORK_TIMEOUT]);
        self::assertSame(30, LdapStub::$options[LDAP_OPT_TIMELIMIT]);
        self::assertSame(1, LdapStub::$closedConnections);
        self::assertSame(1, LdapStub::$freedResults);
    }

    public function test_staff_search_uses_service_account_and_reads_every_page(): void
    {
        LdapStub::$pages = [
            ['entries' => [$this->entry()], 'cookie' => 'next-page'],
            ['entries' => [$this->entry('bob')]],
        ];
        $ldap = new Ldap($this->config + ['MAIL_USERNAME' => 'mail-account', 'MAIL_PASSWORD' => 'mail-password']);

        $users = $ldap->searchUsers('alice');

        self::assertSame(['alice', 'bob'], array_column($users, 'account_name'));
        self::assertSame('alice@stjohnshospital.ie', $users[0]['email']);
        self::assertSame(['bind', 'stjohnshospital\\generic.services', 'test-service-password'], LdapStub::$events[1]);
        self::assertCount(2, LdapStub::$searches);
        self::assertSame('dc=stjohnshospital,dc=ie', LdapStub::$searches[0]['baseDn']);
        self::assertSame('(&(&(objectCategory=person)(objectClass=user)(sAMAccountName=*))(|(displayName=*alice*)(sAMAccountName=*alice*)))', LdapStub::$searches[0]['filter']);
        self::assertSame(['size' => 500, 'cookie' => ''], LdapStub::$searches[0]['controls'][0]['value']);
        self::assertSame(['size' => 500, 'cookie' => 'next-page'], LdapStub::$searches[1]['controls'][0]['value']);
        self::assertSame(30, LdapStub::$searches[0]['timeLimit']);
        self::assertSame(2, LdapStub::$freedResults);
        self::assertSame(1, LdapStub::$closedConnections);
    }

    public function test_custom_formats_filters_page_size_and_timeout_are_used(): void
    {
        $this->config['LDAP_BIND_FORMAT'] = '{username}@login.example.ie';
        $this->config['LDAP_USER_FILTER'] = '(&(objectClass=user)(mail={username}))';
        $this->config['LDAP_SYNC_BIND_FORMAT'] = '{username}@services.example.ie';
        $this->config['LDAP_SYNC_USER_FILTER'] = '(&(objectClass=user)(department=Clinical))';
        $this->config['LDAP_SYNC_PAGE_SIZE'] = '123';
        $this->config['LDAP_SYNC_TIMEOUT'] = '7';
        $ldap = new Ldap($this->config);
        $ldap->authenticate('alice', 'password');
        LdapStub::$pages = [['entries' => []]];
        $ldap->searchUsers('alice');

        self::assertSame('alice@login.example.ie', LdapStub::$events[1][1]);
        self::assertSame('generic.services@services.example.ie', LdapStub::$events[3][1]);
        self::assertSame('(&(objectClass=user)(mail=alice))', LdapStub::$searches[0]['filter']);
        self::assertSame('(&(&(objectClass=user)(department=Clinical))(|(displayName=*alice*)(sAMAccountName=*alice*)))', LdapStub::$searches[1]['filter']);
        self::assertSame(123, LdapStub::$searches[1]['controls'][0]['value']['size']);
        self::assertSame(7, LdapStub::$searches[1]['timeLimit']);
        self::assertSame(7, LdapStub::$options[LDAP_OPT_NETWORK_TIMEOUT]);
    }

    public function test_login_and_staff_search_escape_ldap_filter_metacharacters(): void
    {
        $ldap = new Ldap($this->config);
        $ldap->authenticate('alice*)(sAMAccountName=*)', 'password');
        LdapStub::$pages = [['entries' => []]];
        $ldap->searchUsers("O'Connor*()\\\0");

        self::assertSame('(sAMAccountName=alice\2a\29\28sAMAccountName=\2a\29)', LdapStub::$searches[0]['filter']);
        self::assertStringContainsString("(displayName=*O'Connor\\2a\\28\\29\\5c\\00*)", LdapStub::$searches[1]['filter']);
    }

    public function test_start_tls_runs_before_binding(): void
    {
        $this->config['LDAP_START_TLS'] = 'true';
        (new Ldap($this->config))->authenticate('alice', 'password');

        self::assertSame(['global-option', 'connect', 'tls', 'bind'], array_column(LdapStub::$events, 0));
        self::assertSame(LDAP_OPT_X_TLS_DEMAND, LdapStub::$globalOptions[LDAP_OPT_X_TLS_REQUIRE_CERT]);
    }

    #[DataProvider('tlsConnections')]
    public function test_ca_trust_is_configured_globally_before_login_or_directory_connection(string $operation, string $host, bool $startTls): void
    {
        $this->config['LDAP_HOST'] = $host;
        unset($this->config['LDAP_PORT']);
        $this->config['LDAP_START_TLS'] = $startTls ? 'true' : 'false';
        // The stub checks option ordering; native TLS validation is tested separately.
        $this->config['LDAP_TLS_CA_FILE'] = __FILE__;
        (new Ldap($this->config))->$operation('alice', 'password');

        self::assertSame([
            ['global-option', LDAP_OPT_X_TLS_CACERTFILE, __FILE__],
            ['global-option', LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND],
            ['connect', $host.':'.($startTls ? 389 : 636)],
        ], array_slice(LdapStub::$events, 0, 3));
        self::assertSame($startTls, in_array('tls', array_column(LdapStub::$events, 0), true));
        self::assertContains('bind', array_column(LdapStub::$events, 0));
        self::assertSame(1, LdapStub::$closedConnections);
    }

    public static function tlsConnections(): array
    {
        return [
            ['authenticate', 'ldap://dc.example.ie', true],
            ['authenticate', 'ldaps://dc.example.ie', false],
            ['searchUsers', 'ldap://dc.example.ie', true],
            ['searchUsers', 'ldaps://dc.example.ie', false],
        ];
    }

    public function test_missing_ca_file_fails_before_connecting_or_submitting_credentials(): void
    {
        $this->config['LDAP_START_TLS'] = 'true';
        $this->config['LDAP_TLS_CA_FILE'] = __DIR__.'/missing-ca-cert.pem';
        try {
            (new Ldap($this->config))->authenticate('alice', 'password');
            self::fail('A missing CA file must prevent authentication.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('LDAP_TLS_CA_FILE must point to a readable PEM CA certificate file', $error->getMessage());
        }
        self::assertSame([], LdapStub::$events);
    }

    #[DataProvider('tlsOptions')]
    public function test_rejected_tls_options_fail_before_connecting_or_submitting_credentials(int $option): void
    {
        $this->config['LDAP_START_TLS'] = 'true';
        $this->config['LDAP_TLS_CA_FILE'] = __FILE__;
        LdapStub::$rejectedOption = $option;
        try {
            (new Ldap($this->config))->authenticate('alice', 'password');
            self::fail('A rejected TLS option must prevent authentication.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('Unable to', $error->getMessage());
        }
        self::assertNotContains('connect', array_column(LdapStub::$events, 0));
        self::assertNotContains('bind', array_column(LdapStub::$events, 0));
    }

    public static function tlsOptions(): array
    {
        return [[LDAP_OPT_X_TLS_CACERTFILE], [LDAP_OPT_X_TLS_REQUIRE_CERT]];
    }

    public function test_failed_start_tls_closes_connection_without_binding(): void
    {
        $this->config['LDAP_START_TLS'] = 'true';
        LdapStub::$tlsSucceeds = false;
        LdapStub::$errorCode = -11;
        LdapStub::$diagnosticMessage = "certificate verify failed\nunable to get local issuer certificate";
        try {
            (new Ldap($this->config))->authenticate('alice', 'password');
            self::fail('A failed TLS negotiation must stop authentication.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Unable to start LDAP TLS (error -11: Connect error).', $e->getMessage());
            self::assertStringContainsString('Diagnostic: certificate verify failed unable to get local issuer certificate', $e->getMessage());
        }
        self::assertSame(['global-option', 'connect', 'tls'], array_column(LdapStub::$events, 0));
        self::assertSame(1, LdapStub::$closedConnections);
    }

    #[DataProvider('connectionUris')]
    public function test_hostnames_uris_and_ports(string $host, ?string $port, string $expected): void
    {
        $this->config['LDAP_HOST'] = $host;
        unset($this->config['LDAP_PORT']);
        if ($port !== null) {
            $this->config['LDAP_PORT'] = $port;
        }
        (new Ldap($this->config))->authenticate('alice', 'password');

        self::assertContains(['connect', $expected], LdapStub::$events);
    }

    public static function connectionUris(): array
    {
        return [
            ['dc.example.ie', '1389', 'ldap://dc.example.ie:1389'],
            ['ldap://dc.example.ie:1389/', null, 'ldap://dc.example.ie:1389'],
            ['ldap://dc.example.ie:1389', '389', 'ldap://dc.example.ie:389'],
            ['ldaps://dc.example.ie', null, 'ldaps://dc.example.ie:636'],
        ];
    }

    #[DataProvider('ldapOperations')]
    public function test_disabled_ldap_never_connects(string $operation): void
    {
        $this->config['LDAP_ENABLED'] = 'false';
        try {
            (new Ldap($this->config))->$operation('alice', 'password');
            self::fail('Disabled LDAP must not allow this operation.');
        } catch (RuntimeException $e) {
            self::assertSame('LDAP is disabled.', $e->getMessage());
        }
        self::assertSame([], LdapStub::$events);
    }

    public static function ldapOperations(): array
    {
        return [['authenticate'], ['searchUsers']];
    }

    public function test_invalid_password_returns_null_and_closes_connection(): void
    {
        LdapStub::$bindSucceeds = false;
        LdapStub::$errorCode = 49;

        self::assertNull((new Ldap($this->config))->authenticate('alice', 'wrong-password'));
        self::assertSame([], LdapStub::$searches);
        self::assertSame(1, LdapStub::$closedConnections);
    }

    public function test_connection_failure_is_not_reported_as_invalid_credentials(): void
    {
        LdapStub::$bindSucceeds = false;
        LdapStub::$errorCode = 81;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LDAP user bind failed (error 81:');
        (new Ldap($this->config))->authenticate('alice', 'password');
    }

    public function test_directory_bind_failure_preserves_server_diagnostic(): void
    {
        LdapStub::$bindSucceeds = false;
        LdapStub::$errorCode = 8;
        LdapStub::$diagnosticMessage = 'The server requires binds to turn on integrity checking.';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Diagnostic: The server requires binds to turn on integrity checking.');
        (new Ldap($this->config))->searchUsers('alice');
    }

    public function test_blank_credentials_never_bind_anonymously(): void
    {
        $ldap = new Ldap($this->config);
        self::assertNull($ldap->authenticate('alice', ''));
        self::assertNull($ldap->authenticate(' ', 'password'));
        self::assertSame([], LdapStub::$events);
    }

    public function test_legacy_config_still_supports_login_and_directory_lookup(): void
    {
        $ldap = new Ldap([
            'LDAP_SERVER' => 'old.example.ie', 'DOMAIN' => 'legacy',
            'LDAP_BASE_DN' => 'dc=example,dc=ie',
            'MAIL_USERNAME' => 'mail-account', 'MAIL_PASSWORD' => 'mail-password',
        ]);
        $ldap->authenticate('alice', 'password');
        LdapStub::$pages = [['entries' => []]];
        $ldap->searchUsers('alice');

        self::assertSame(['connect', 'ldap://old.example.ie:389'], LdapStub::$events[0]);
        self::assertSame(['bind', 'legacy\\alice', 'password'], LdapStub::$events[1]);
        self::assertSame(['bind', 'legacy\\mail-account', 'mail-password'], LdapStub::$events[3]);
    }

    public function test_ldap_domain_supplies_default_bind_formats(): void
    {
        unset($this->config['LDAP_BIND_FORMAT'], $this->config['LDAP_SYNC_BIND_FORMAT']);
        $ldap = new Ldap($this->config + ['DOMAIN' => 'old-domain']);
        $ldap->authenticate('alice', 'password');
        LdapStub::$pages = [['entries' => []]];
        $ldap->searchUsers('alice');

        self::assertSame('stjohnshospital\\alice', LdapStub::$events[1][1]);
        self::assertSame('stjohnshospital\\generic.services', LdapStub::$events[3][1]);
    }

    #[DataProvider('syncCredentialKeys')]
    public function test_incomplete_service_credentials_never_use_mail_password(string $missing): void
    {
        $this->config[$missing] = '';
        $ldap = new Ldap($this->config + ['MAIL_USERNAME' => 'mail-account', 'MAIL_PASSWORD' => 'mail-password']);
        try {
            $ldap->searchUsers('alice');
            self::fail('An incomplete service account must not bind.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('requires a bind username and password', $e->getMessage());
        }
        self::assertSame(['connect'], array_column(LdapStub::$events, 0));
        self::assertSame(1, LdapStub::$closedConnections);
    }

    public static function syncCredentialKeys(): array
    {
        return [['LDAP_SYNC_BIND_USERNAME'], ['LDAP_SYNC_BIND_PASSWORD']];
    }

    public function test_email_fallback_preserves_existing_ad_mail(): void
    {
        $ldap = new Ldap($this->config);
        self::assertSame('alice@stjohnshospital.ie', $ldap->email($this->entry()));
        self::assertSame('alice@another.example.ie', $ldap->email(['mail' => ['alice@another.example.ie']] + $this->entry()));
        self::assertSame('No email found', (new Ldap([]))->email($this->entry()));
    }

    public function test_blank_query_does_not_bind_or_search_entire_directory(): void
    {
        self::assertSame([], (new Ldap($this->config))->searchUsers(' '));
        self::assertSame(['connect'], array_column(LdapStub::$events, 0));
        self::assertSame([], LdapStub::$searches);
        self::assertSame(1, LdapStub::$closedConnections);
    }

    public function test_partial_search_results_are_rejected_and_freed(): void
    {
        LdapStub::$pages = [['entries' => [$this->entry()], 'code' => 4]];
        try {
            (new Ldap($this->config))->searchUsers('alice');
            self::fail('A size-limited partial result must not appear complete.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('incomplete result (error 4)', $e->getMessage());
        }
        self::assertSame(1, LdapStub::$freedResults);
        self::assertSame(1, LdapStub::$closedConnections);
    }

    public function test_failed_search_closes_connection(): void
    {
        LdapStub::$pages = [false];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LDAP search failed');
        try {
            (new Ldap($this->config))->searchUsers('alice');
        } finally {
            self::assertSame(1, LdapStub::$closedConnections);
        }
    }

    public function test_ambiguous_login_profile_cannot_authenticate(): void
    {
        LdapStub::$pages = [['entries' => [$this->entry(), $this->entry('bob')]]];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('matched more than one user');
        (new Ldap($this->config))->authenticate('alice', 'password');
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_fail_explicitly(string $key, string $value): void
    {
        $this->config[$key] = $value;
        $this->expectException(RuntimeException::class);
        (new Ldap($this->config))->searchUsers('alice');
    }

    public static function invalidSettings(): array
    {
        return [
            ['LDAP_ENABLED', 'perhaps'],
            ['LDAP_START_TLS', 'perhaps'],
            ['LDAP_HOST', 'https://dc.example.ie'],
            ['LDAP_PORT', '65536'],
            ['LDAP_SYNC_TIMEOUT', '0'],
            ['LDAP_SYNC_PAGE_SIZE', '0'],
            ['LDAP_BASE_DN', ''],
            ['LDAP_SYNC_BIND_FORMAT', 'fixed-account'],
        ];
    }
}
