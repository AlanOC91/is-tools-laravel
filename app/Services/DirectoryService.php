<?php

namespace App\Services;

use LDAP\Connection;
use RuntimeException;

class DirectoryService
{
    private ?Connection $connection = null;

    public function __construct(private array $config) {}

    public static function fromConfig(): self
    {
        $mapping = [
            'LDAP_ENABLED' => 'enabled', 'LDAP_HOST' => 'host', 'LDAP_PORT' => 'port',
            'LDAP_START_TLS' => 'start_tls', 'LDAP_TLS_CA_FILE' => 'ca_file',
            'LDAP_BASE_DN' => 'base_dn', 'LDAP_BIND_FORMAT' => 'bind_format',
            'LDAP_USER_FILTER' => 'user_filter', 'LDAP_EMAIL_DOMAIN' => 'email_domain',
            'LDAP_SYNC_BIND_USERNAME' => 'search_username', 'LDAP_SYNC_BIND_PASSWORD' => 'search_password',
            'LDAP_SYNC_BIND_FORMAT' => 'search_bind_format', 'LDAP_SYNC_USER_FILTER' => 'search_filter',
            'LDAP_SYNC_PAGE_SIZE' => 'page_size', 'LDAP_SYNC_TIMEOUT' => 'timeout',
        ];
        $values = [];
        foreach ($mapping as $key => $setting) {
            $value = config('directory.'.$setting);
            $values[$key] = $value === null ? false : (is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        return new self($values);
    }

    /** Return the authenticated directory entry, or null for invalid credentials. */
    public function verifyTransport(): void
    {
        try {
            $this->connect();
        } finally {
            $this->close();
        }
    }

    public function authenticate(string $username, string $password): ?array
    {
        // Empty passwords must never result in an anonymous LDAP bind.
        if (trim($username) === '' || $password === '') {
            return null;
        }

        try {
            $this->connect();
            if (! @ldap_bind($this->connection, $this->bindName($username), $password)) {
                $code = ldap_errno($this->connection);
                if ($code === 49) {
                    return null;
                }
                throw $this->connectionFailure('LDAP user bind failed');
            }

            $template = $this->value('LDAP_USER_FILTER', '(sAMAccountName={username})');
            if (! str_contains($template, '{username}')) {
                throw new RuntimeException('LDAP_USER_FILTER must contain {username}.');
            }
            $filter = str_replace('{username}', ldap_escape($username, '', LDAP_ESCAPE_FILTER), $template);
            $entries = $this->search($filter);
            if (count($entries) > 1) {
                throw new RuntimeException('LDAP_USER_FILTER matched more than one user.');
            }

            return $entries[0] ?? null;
        } finally {
            $this->close();
        }
    }

    /** Search the directory for meeting presenters and staff, using paged results. */
    public function searchUsers(string $query): array
    {
        try {
            $this->connect();
            if (trim($query) === '') {
                return [];
            }

            // Only legacy configurations may reuse the mail account. Do not mix credentials.
            $hasSyncCredentials = $this->value('LDAP_SYNC_BIND_USERNAME') !== ''
                || $this->value('LDAP_SYNC_BIND_PASSWORD') !== '';
            $username = $hasSyncCredentials
                ? $this->value('LDAP_SYNC_BIND_USERNAME') : $this->value('MAIL_USERNAME');
            $password = $hasSyncCredentials
                ? $this->value('LDAP_SYNC_BIND_PASSWORD') : $this->value('MAIL_PASSWORD');
            if (trim($username) === '' || $password === '') {
                throw new RuntimeException('LDAP directory search requires a bind username and password.');
            }
            if (! @ldap_bind($this->connection, $this->bindName($username, true), $password)) {
                throw $this->connectionFailure('LDAP directory bind failed');
            }

            $query = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
            $userFilter = $this->value('LDAP_SYNC_USER_FILTER', '(&(objectCategory=person)(objectClass=user)(sAMAccountName=*))');
            $filter = '(&'.$userFilter.'(|(displayName=*'.$query.'*)(sAMAccountName=*'.$query.'*)))';
            $users = [];
            foreach ($this->search($filter, true) as $entry) {
                $users[] = [
                    'name' => $entry['displayname'][0] ?? $entry['cn'][0] ?? 'Unknown',
                    'email' => $this->email($entry),
                    'account_name' => $entry['samaccountname'][0] ?? 'Unknown',
                ];
            }

            return $users;
        } finally {
            $this->close();
        }
    }

    public function email(array $entry): string
    {
        if (! empty($entry['mail'][0])) {
            return $entry['mail'][0];
        }
        $domain = trim($this->value('LDAP_EMAIL_DOMAIN'));
        if ($domain !== '' && ! empty($entry['samaccountname'][0])) {
            return $entry['samaccountname'][0].'@'.$domain;
        }

        return 'No email found';
    }

    private function connect(): void
    {
        if (! $this->boolean('LDAP_ENABLED', true)) {
            throw new RuntimeException('LDAP is disabled.');
        }
        if (! extension_loaded('ldap')) {
            throw new RuntimeException('The PHP LDAP extension is not enabled.');
        }

        $host = trim($this->value('LDAP_HOST', '', 'LDAP_SERVER'));
        $uri = parse_url(str_contains($host, '://') ? $host : 'ldap://'.$host);
        if (! $uri || empty($uri['host']) || ! in_array($uri['scheme'] ?? '', ['ldap', 'ldaps'], true)
            || isset($uri['user']) || isset($uri['pass']) || isset($uri['query'])
            || isset($uri['fragment']) || ! in_array($uri['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('LDAP_HOST must be a hostname or an ldap:// or ldaps:// URI.');
        }
        $defaultPort = $uri['port'] ?? ($uri['scheme'] === 'ldaps' ? 636 : 389);
        $port = $this->positiveInteger('LDAP_PORT', $defaultPort);
        if ($port > 65535) {
            throw new RuntimeException('LDAP_PORT must be between 1 and 65535.');
        }
        $startTls = $this->boolean('LDAP_START_TLS', false);
        if ($startTls && $uri['scheme'] === 'ldaps') {
            throw new RuntimeException('LDAP_START_TLS requires an ldap:// connection.');
        }
        $timeout = $this->positiveInteger('LDAP_SYNC_TIMEOUT', 30);
        if ($startTls || $uri['scheme'] === 'ldaps') {
            // OpenLDAP has its own trust configuration, separate from openssl.cafile.
            // Set TLS options globally before creating the connection, including for LDAPS.
            $caFile = trim($this->value('LDAP_TLS_CA_FILE'));
            if ($caFile !== '') {
                if (! is_file($caFile) || ! is_readable($caFile)) {
                    throw new RuntimeException('LDAP_TLS_CA_FILE must point to a readable PEM CA certificate file: '.$caFile);
                }
                if (! @ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $caFile)) {
                    throw new RuntimeException('Unable to configure the LDAP TLS CA file.');
                }
            }
            if (! @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND)) {
                throw new RuntimeException('Unable to require LDAP TLS certificate verification.');
            }
        }
        $connection = @ldap_connect($uri['scheme'].'://'.$uri['host'].':'.$port);
        if ($connection === false) {
            throw new RuntimeException('Unable to initialize the LDAP connection.');
        }
        $this->connection = $connection;
        foreach ([LDAP_OPT_PROTOCOL_VERSION => 3, LDAP_OPT_REFERRALS => 0, LDAP_OPT_NETWORK_TIMEOUT => $timeout, LDAP_OPT_TIMELIMIT => $timeout] as $option => $value) {
            if (! ldap_set_option($connection, $option, $value)) {
                throw new RuntimeException('Unable to configure the LDAP connection.');
            }
        }
        if ($startTls && ! @ldap_start_tls($connection)) {
            throw $this->connectionFailure('Unable to start LDAP TLS');
        }
    }

    /** Internal details are logged by the endpoints, never returned in their JSON errors. */
    private function connectionFailure(string $message): RuntimeException
    {
        $message .= ' (error '.ldap_errno($this->connection).': '.ldap_error($this->connection).').';
        $diagnostic = '';
        if (@ldap_get_option($this->connection, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic)
            && is_string($diagnostic) && trim($diagnostic) !== '') {
            $message .= ' Diagnostic: '.preg_replace('/[\r\n]+/', ' ', trim($diagnostic));
        }

        return new RuntimeException($message);
    }

    private function bindName(string $username, bool $sync = false): string
    {
        $domain = $this->value('LDAP_DOMAIN', '', 'DOMAIN');
        $format = $this->value('LDAP_BIND_FORMAT', $domain !== '' ? $domain.'\\{username}' : '{username}');
        if ($sync) {
            $format = $this->value('LDAP_SYNC_BIND_FORMAT', $format);
        }
        if (! str_contains($format, '{username}')) {
            throw new RuntimeException('LDAP bind formats must contain {username}.');
        }

        return str_replace('{username}', $username, $format);
    }

    private function search(string $filter, bool $paged = false): array
    {
        $baseDn = trim($this->value('LDAP_BASE_DN'));
        if ($baseDn === '') {
            throw new RuntimeException('LDAP_BASE_DN is required.');
        }
        $timeout = $this->positiveInteger('LDAP_SYNC_TIMEOUT', 30);
        $pageSize = $paged ? $this->positiveInteger('LDAP_SYNC_PAGE_SIZE', 500) : 0;
        $cookie = '';
        $entries = [];
        do {
            $controls = $paged ? [[
                'oid' => LDAP_CONTROL_PAGEDRESULTS,
                'iscritical' => true,
                'value' => ['size' => $pageSize, 'cookie' => $cookie],
            ]] : [];
            $result = @ldap_search(
                $this->connection, $baseDn, $filter,
                ['displayName', 'cn', 'sAMAccountName', 'mail', 'memberOf'],
                0, 0, $timeout, LDAP_DEREF_NEVER, $controls
            );
            if ($result === false) {
                throw $this->connectionFailure('LDAP search failed');
            }
            try {
                $responseControls = [];
                $code = 0;
                $matchedDn = $message = '';
                $referrals = [];
                if (! ldap_parse_result($this->connection, $result, $code, $matchedDn, $message, $referrals, $responseControls) || $code !== 0) {
                    throw new RuntimeException('LDAP search returned an incomplete result (error '.$code.').');
                }
                $page = ldap_get_entries($this->connection, $result);
                if ($page === false) {
                    throw new RuntimeException('Unable to read LDAP search results.');
                }
                for ($i = 0; $i < $page['count']; $i++) {
                    $entries[] = $page[$i];
                }
                $cookie = $paged ? ($responseControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '') : '';
            } finally {
                ldap_free_result($result);
            }
        } while ($cookie !== '');

        return $entries;
    }

    private function close(): void
    {
        if ($this->connection !== null) {
            @ldap_unbind($this->connection);
            $this->connection = null;
        }
    }

    private function value(string $key, string $default = '', ?string $legacy = null): string
    {
        $value = $this->config[$key] ?? false;
        if ($value === false && $legacy !== null) {
            $value = $this->config[$legacy] ?? false;
        }

        return $value === false ? $default : (string) $value;
    }

    private function boolean(string $key, bool $default): bool
    {
        $value = filter_var($this->value($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            throw new RuntimeException($key.' must be true or false.');
        }

        return $value;
    }

    private function positiveInteger(string $key, int $default): int
    {
        $value = filter_var($this->value($key, (string) $default), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new RuntimeException($key.' must be a positive integer.');
        }

        return $value;
    }
}
