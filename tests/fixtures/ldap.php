<?php

namespace Tests\Unit {
    /** LDAP responses for tests; no bind or search reaches a domain controller. */
    class LdapStub
    {
        public static array $events;

        public static array $options;

        public static array $globalOptions;

        public static array $searches;

        public static array $pages;

        public static bool $bindSucceeds;

        public static bool $tlsSucceeds;

        public static int $errorCode;

        public static int $freedResults;

        public static int $closedConnections;

        public static ?int $rejectedOption;

        public static string $diagnosticMessage;

        public static function reset(): void
        {
            self::$events = self::$options = self::$globalOptions = self::$searches = [];
            self::$pages = [['entries' => []]];
            self::$bindSucceeds = self::$tlsSucceeds = true;
            self::$errorCode = self::$freedResults = self::$closedConnections = 0;
            self::$rejectedOption = null;
            self::$diagnosticMessage = '';
        }
    }
}

namespace App\Services {
    use Tests\Unit\LdapStub;

    function ldap_connect($uri)
    {
        LdapStub::$events[] = ['connect', $uri];

        // Native ldap_connect initializes a connection without opening a socket.
        return \ldap_connect('ldap://127.0.0.1:389');
    }

    function ldap_set_option($connection, $option, $value)
    {
        if ($connection === null) {
            LdapStub::$globalOptions[$option] = $value;
            LdapStub::$events[] = ['global-option', $option, $value];

            // Do not change the real process-wide TLS configuration during stubbed tests.
            return $option !== LdapStub::$rejectedOption;
        }
        LdapStub::$options[$option] = $value;

        return \ldap_set_option($connection, $option, $value);
    }

    function ldap_get_option($connection, $option, &$value)
    {
        if ($option === LDAP_OPT_DIAGNOSTIC_MESSAGE) {
            $value = LdapStub::$diagnosticMessage;

            return true;
        }

        return \ldap_get_option($connection, $option, $value);
    }

    function ldap_error($connection)
    {
        return \ldap_err2str(LdapStub::$errorCode);
    }

    function ldap_start_tls($connection)
    {
        LdapStub::$events[] = ['tls'];

        return LdapStub::$tlsSucceeds;
    }

    function ldap_bind($connection, $username, $password)
    {
        LdapStub::$events[] = ['bind', $username, $password];

        return LdapStub::$bindSucceeds;
    }

    function ldap_errno($connection)
    {
        return LdapStub::$errorCode;
    }

    function ldap_search($connection, $baseDn, $filter, $attributes, $attributesOnly, $sizeLimit, $timeLimit, $deref, $controls)
    {
        LdapStub::$searches[] = compact('baseDn', 'filter', 'attributes', 'timeLimit', 'controls');
        if (LdapStub::$pages === []) {
            throw new \LogicException('Unexpected LDAP page request.');
        }

        return array_shift(LdapStub::$pages);
    }

    function ldap_parse_result($connection, $result, &$code, &$matchedDn, &$message, &$referrals, &$controls)
    {
        $code = $result['code'] ?? 0;
        $controls = [LDAP_CONTROL_PAGEDRESULTS => ['value' => ['cookie' => $result['cookie'] ?? '']]];

        return true;
    }

    function ldap_get_entries($connection, $result)
    {
        return ['count' => count($result['entries'])] + $result['entries'];
    }

    function ldap_free_result($result)
    {
        LdapStub::$freedResults++;

        return true;
    }

    function ldap_unbind($connection)
    {
        LdapStub::$closedConnections++;

        return \ldap_unbind($connection);
    }
}
