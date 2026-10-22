# IS Tools

St. John's Hospital IT request system, rebuilt in Laravel 13, Blade, Tailwind CSS and Alpine.js. Production URL: **https://apps:3071/**. Requires PHP 8.4+; the Docker image includes Apache, LDAP, MySQL and the frontend build. The interface uses hospital blue and the same logo as Document Compliance. I.S. Support extension: **233**.

The application uses the **existing `is_tools` database**, with the original IDs, tables, request states, group memberships and meeting history. Migrations adopt existing tables without rebuilding them. Additional submission, audit and notification tables support new requests. No production database connection or production write was used during development.

## Functionality

- Guest hospital login, NIMIS and iLab account requests, including drawn signatures and declarations.
- Staff department-folder access, equipment loans, software access, joining, moving and leaving forms.
- IS-only cyber incident reports, request completion/rejection, asset tags, reference-data management, notification delivery and document archive.
- Meeting attendance with two presenters, directory autocomplete, optional meeting names and session dates.
- HR/doctor reports with inclusive date ranges, attendance and presentation counts, CSV export and printing. All signed-in staff retain access, as in the previous application.
- Hospital applications, Microsoft 365 shortcuts, about and wellbeing pages; redirects for legacy PHP bookmarks.
- Active Directory login and `I.S. Staff` administration permissions, using existing users and groups. No local password or default administrator account.
- Durable email outbox with retries and visible failure status; iLab and cyber reports generate private PDFs through Gotenberg.

## Start here

See [deployment and cutover](docs/deployment.md), [legacy feature and permission mapping](docs/legacy-inventory.md), and [verification results](docs/verification.md).

On the production host:

1. Configure `.env` using `.env.example`, retaining the existing database and mail connection details.
2. Put the hospital `apps.crt`, `apps.key` and CA certificate in `ssl/`; see [certificate requirements](ssl/README.md).
3. Transfer the **production** legacy `documents/` directory, preserving its subdirectories and filenames.
4. Back up the database and documents, then follow the cutover sequence in the deployment guide.

```powershell
docker compose build web
docker compose up -d
docker compose exec web php artisan is-tools:check --directory
```

Compose runs an additive migration service before starting Apache, the queue worker and scheduler. TLS terminates in Apache on port 3071, following `sjl-document-compliance`. The production Compose file does not create a database service. Gotenberg has no published host port.

## Persistent signatures and saved forms

All signatures and saved form PDFs use the private `documents` disk. Docker mounts the repository's **host `documents/` folder** at `/var/www/html/documents` in the web, queue, scheduler and migration containers. Files survive image rebuilds, container recreation and `docker compose down` / `up`.

| Host location | Contents |
| --- | --- |
| `documents/signatures/ad/` | Hospital login signatures |
| `documents/signatures/nimis/` | NIMIS signatures |
| `documents/signatures/ilab/` | iLab signatures |
| `documents/signatures/assetreq/` | Equipment-loan signatures |
| `documents/pdf/` | Saved iLab, cyber incident and legacy form PDFs |

Copy the old production `documents/` **contents into this project's host `documents/` directory**, keeping filenames and subfolders unchanged. The old and new files use the same persistent mount. Existing records reference those filenames; no re-import of the database is needed for this file transfer. New form details and request history remain in the shared database.

With the standard Docker configuration, leave `DOCUMENTS_PATH` unset or set it to `/var/www/html/documents`. A different path needs its own persistent mount. Back up the host `documents/` directory with the database. Documents remain private and are served through authorised application routes.

## Existing environment settings

The application accepts `DATABASE_HOST_1`, `DATABASE_NAME_1`, `DATABASE_USER_1`, `DATABASE_PASSWORD_1`, `LDAP_SERVER`, `DOMAIN`, `MAIL_SERVER` and `FROM_EMAIL` as aliases. Laravel names take precedence. Use `DB_CONNECTION=mysql`.

To prepare a **new private file** from the old `.env`, without printing secrets or overwriting files:

```powershell
php artisan is-tools:configure-legacy C:\xampp_8.1\htdocs\SJL\is-tools\.env
```

This produces ignored `.env.production.local`. Review it before copying it to `.env` on the server. It translates loopback database hosts to `host.docker.internal`, keeps StartTLS enabled, uses the container CA bundle and new Gotenberg service, and generates a new application key. Keep that key stable after deployment. The converter preserves the old directory-search service-account fallback as a complete credential pair; new installations should use dedicated `LDAP_SYNC_BIND_*` settings.

## Local development and verification

```powershell
composer install
npm ci
npm run build
php artisan test
```

Tests use an in-memory SQLite database, isolated file storage, LDAP stubs and fake/array mail. They never connect to AD or send SMTP mail.

To inspect the supplied export in an isolated local MariaDB container:

```powershell
docker compose -f compose.verify.yaml up -d
# Configure .env for 127.0.0.1:33371, database is_tools,
# root / verification-only, APP_ENV=local, MAIL_MAILER=log, LDAP_ENABLED=false.
php artisan key:generate
php artisan is-tools:legacy-audit --snapshot=storage/app/before.json
php artisan migrate
php artisan is-tools:legacy-audit --compare=storage/app/before.json
php artisan serve --host=127.0.0.1 --port=8071
```

`compose.verify.yaml` imports `is_tools.sql` only when its dedicated volume is first created. The dump, private environment files, certificates and documents are excluded from Git and application images. The supplied export remains untouched at the repository root.

For local HTTPS, `scripts/New-DevelopmentCertificate.ps1` creates an untrusted, short-lived certificate without overwriting existing certificates. The production host requires the hospital-issued certificate.
