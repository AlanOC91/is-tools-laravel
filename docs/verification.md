# Verification — 22 October 2026

## Automated application checks

58 tests pass with 338 assertions.

The regression suite exercises all ten forms, guest/staff/IS permissions, stored signatures, request ownership, HTML sanitisation, asset tags, one-way request processing, LDAP authentication failure, remember tokens, lookup auditing, meetings, both presenters, distinct attendance and inclusive date reports. CSV formula escaping and notification recovery after a PDF outage are covered. Legacy LDAP tests were ported with their stubs: TLS trust ordering, certificate verification, exact passwords, escaped filters, paged searches, complete credential pairs, connection cleanup and incomplete search results.

Environment conversion is checked with password characters requiring quoting and confirms existing files are not overwritten. Frontend production build, Blade compilation, Composer validation, PHP formatting and Compose configuration are checked separately.

## Full exported database

Imported the supplied `is_tools.sql` into a separate local MariaDB 10.11 container. No live hospital database was contacted. Compared ordered SHA-256 row hashes and counts before and after Laravel migrations, using the original column lists:

| Table | Rows | Migration comparison |
| --- | ---: | --- |
| data_log | 8 | Unchanged |
| departments | 42 | Unchanged |
| form_requests | 443 | Unchanged |
| ilab_requests | 101 | Unchanged |
| login_log | 575 | Unchanged |
| meetings | 328 | Unchanged |
| meeting_users | 2,757 | Unchanged |
| requests | 511 | Unchanged |
| security_groups | 359 | Unchanged |
| security_groups_users | 1,799 | Unchanged |
| session_rememberme | 520 | Unchanged |
| software | 17 | Unchanged |
| verified_logins | 93 | Unchanged |
| **Total** | **7,553** | **All unchanged** |

The SQL export is unchanged and excluded from Git. Source documents copied from the local legacy checkout are also private and excluded. **All 443 production signature references are missing from that local file set.** Transfer the production documents directory before cutover.

## Docker and runtime

Built the multi-stage image successfully. Started the migration, Apache HTTPS, queue, scheduler and Gotenberg services against the isolated database. HTTPS on `https://localhost:3071/` returned 200 and the web container passed its health check. Runtime: PHP 8.4.27 / Laravel 13.38.0. Production host remains `https://apps:3071/`.

`tests/runtime-smoke.php` was copied into the verification container and run from `/var/www/html`. It saved a synthetic iLab submission in a MariaDB transaction, persisted a signature, generated a real 100 KB PDF through Gotenberg and composed both support and iLab mail through the array transport. Verification rows were rolled back and only its generated signature/PDF files were removed. A subsequent legacy hash comparison again showed every legacy row unchanged. No SMTP message was sent.

Inspected the guest portal at desktop and narrow/mobile widths and exercised the signature canvas with pointer events. The hidden signature received a PNG, with no browser console errors. Automated HTTP tests render the authenticated pages and all forms.

The final container also rendered the overview, request list and historical request details, meeting list and historical meeting details, reference data, notifications, document archive, Microsoft 365 links and attendance report against the real exported records. An actual HTTPS POST without a CSRF token returned 419; guest access to the administration request list redirected to sign-in. Form search correctly filtered the visible cards without horizontal overflow or console errors.

## Server-side acceptance still required

- Hospital-issued `apps` certificate and hospital CA trust for LDAP/SMTP (only self-signed local TLS was available).
- A real AD sign-in, group refresh and directory search using approved hospital credentials.
- Delivery through the actual SMTP relay to support, iLab and governance recipients.
- Transfer and verification of the production signatures/PDFs.
- Live F: drive folder mount if filesystem enumeration rather than department lookup options is required.

These require production files, credentials and network access unavailable in this workspace. The local private production configuration was prepared from the legacy `.env` but has not been used to contact production.
