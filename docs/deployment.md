# Deployment and cutover

## Server layout

The application follows the reference document-compliance project: a multi-stage Node/Composer/PHP-Apache image; read-only environment and certificate mounts; Apache TLS; shared persistent storage; separate migration, queue and scheduler services. PHP 8.4 is used with Laravel 13 and locked dependencies. MySQL/MariaDB stays on the existing database server.

`https://apps:3071/` is the application root. Set `APP_URL=https://apps:3071`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`. Use a unique `SESSION_COOKIE=sjl_is_tools_session`; keep `APP_KEY` stable across all services and later deployments.

## Prepare the configuration

Use `.env.example` or the privately generated `.env.production.local` as the basis of the server `.env`. Confirm the existing database host, port, database name and credentials. `host.docker.internal` means the Docker host; it does not point to another database server. Retain a remote database hostname unchanged. The MySQL account must accept connections from the Docker host/container network and have permission to run the additive migrations.

The legacy `DATABASE_*_1` names remain supported when the corresponding Laravel `DB_*` value is absent. Do not leave placeholder `DB_*` values in front of valid aliases. Use `DB_COLLATION=utf8mb4_general_ci` to match the export. No production database or records are bundled with the image.

Configure `SUPPORT_EMAIL`, `ILAB_EMAIL`, optional iLab CC/BCC, and `IGO_EMAIL` for cyber reports. Use the existing SMTP settings. `MAIL_SCHEME=smtp` supports SMTP with automatic STARTTLS; use `smtps` where the relay requires implicit TLS. The mail account and hospital CA must work from the queue container as well as the web container.

LDAP accepts the same domain/bind conventions as the reference app. Use a domain-controller hostname matching its certificate and StartTLS, or LDAPS with `LDAP_START_TLS=false` and port 636. `LDAP_TLS_CA_FILE=/etc/ssl/certs/ca-certificates.crt` is the container trust bundle. Supply a read-only directory search account with `LDAP_SYNC_BIND_USERNAME`, `LDAP_SYNC_BIND_PASSWORD` and its bind format. When neither setting exists, the complete legacy mail-account pair remains a fallback; an incomplete dedicated pair never mixes with mail credentials.

Install `ssl/apps.crt`, `ssl/apps.key` and `ssl/stjohnshospital-CA-CA.crt`, as described in [ssl/README.md](../ssl/README.md). The entrypoint installs the mounted CA in each service's trust store. Production certificates were not available in the supplied reference checkout. Local verification uses a clearly separate self-signed certificate.

For F: drive folder enumeration, mount the host's accessible `Depts` directory read-only using `compose.folders.example.yaml`, set `DEPARTMENT_FOLDERS_HOST_PATH` on the host and `DEPARTMENT_FOLDERS_PATH=/mnt/department-folders` in `.env`. The Windows UNC path `\\general-1\public-1\Depts` is not automatically available inside Linux containers. Without a mount the form uses the existing department lookup list.

## Transfer the files

Compose bind-mounts that host directory at `/var/www/html/documents` for every application service. It stores both imported files and newly created signatures/PDFs outside the container filesystem, so rebuilding or replacing containers does not remove them. Leave `DOCUMENTS_PATH` unset or use `/var/www/html/documents` with this mount.

Copy the live legacy `documents/` directory to this repository's `documents/`, retaining the complete structure:

```text
documents/
  signatures/
    ad/<sigSlug>.png
    nimis/<sigSlug>.png
    ilab/<sigSlug>.png
    assetreq/<sigSlug>.png
  pdf/
    ...existing PDF filenames and subdirectories...
```

The database dump cannot recreate signature images or generated PDF files. The development checkout's files did not match the supplied production export's signature references; obtain these from the production server or its backup. Run the legacy audit to count missing referenced files after copying. Do not overwrite existing new submissions when synchronising the final legacy files.

## Cutover sequence

1. Schedule a short cutover and stop new writes through the old app. Take a consistent backup of the live database and documents using the existing backup procedure.
2. Deploy this source, configure the server `.env`, install certificates and transfer files.
3. Build the image, then capture the current database hashes before migrating:

```powershell
docker compose build web
docker compose run --rm --no-deps web php artisan is-tools:legacy-audit --snapshot=storage/app/pre-cutover.json
docker compose up -d
docker compose exec web php artisan is-tools:legacy-audit --compare=storage/app/pre-cutover.json
docker compose exec web php artisan is-tools:check --directory
docker compose ps
```

The migration container must finish successfully before web/queue/scheduler start. Existing tables are left intact. Migration rollback deliberately does not drop legacy or operational records. Never use `migrate:fresh`, `db:wipe`, or a demo seeder on the shared database.

4. Open `https://apps:3071/` and verify one guest account request, iLab PDF and recipients, a staff form, IS processing with asset tag, and a doctor/HR meeting report. Use agreed test identities and recipients. Verify both an IS account and an ordinary staff account. Existing users will need to sign in again.
5. Check the Notifications page and queue logs before handing over. Use its test email form for an explicitly requested mail test. Local development verified PDF and mail composition with array/log transports; real hospital LDAP binds, certificate trust and SMTP delivery require server-side validation.
6. Redirect old GET/HEAD bookmarks from `/is-tools/...` to `https://apps:3071/...` in the old server's Apache/proxy configuration. The Laravel app handles the resulting `.php` page aliases. Use a temporary redirect during the initial cutover; make it permanent once accepted. Old GET approval/rejection links must not be re-enabled.

If server configuration changes, recreate services with `docker compose up -d --force-recreate`; restart queue workers after code/config changes. Container code is built into the image, so source changes require a rebuild. Avoid running local PHP and Docker against the same mounted compiled-view cache at once; run `docker compose exec web php artisan view:clear` if switching runtimes.

## Operations

```powershell
docker compose logs --tail=100 web queue scheduler
docker compose exec web php artisan queue:failed
docker compose exec web php artisan is-tools:dispatch-deliveries
docker compose exec web php artisan is-tools:legacy-audit
```

New notifications remain pending until the scheduler queues them (normally within one minute). The worker retries failures three times with backoff. The Notifications page shows failures and allows another attempt after repair. Empty recipient snapshots are filled from the current channel configuration on retry. Sent records are not sent again by the same job. As with ordinary SMTP outboxes, a worker crash after SMTP accepts a message but before commit can result in duplicate delivery; the request reference identifies the same submission.

Back up the **database, `documents/`, `.env`/APP_KEY and certificates**. Persist `storage/` for sessions, logs, compiled views and queue locks. Test restores in an isolated database. Do not run legacy cleanup jobs that delete `verified_logins`: the existing schema contains cascading foreign keys to historical records.

For a code rollback, stop this application's scheduler/queue/web and restore the previous code. The additive columns/tables can remain. Reconcile any new requests received since cutover before restoring an older database backup; blindly restoring it would discard those new records.

## Local verification environment

The prepared local `.env` uses the isolated export at `127.0.0.1:33371`, LDAP disabled and log mail. The ignored `.env.docker.local` points containers at that same disposable database and uses array/log mail, with local self-signed TLS. To use it explicitly:

```powershell
$env:IS_TOOLS_ENV_FILE = './.env.docker.local'
docker compose up -d
```

This override chooses which file is mounted at `/var/www/html/.env`. It is separate from Compose's `--env-file` interpolation. Remove the override on the production host and use its production `.env`. The supplied `.env.production.local` is a prepared configuration only; it has not been used to connect to the hospital database.

The verification database can be stopped with `docker compose -f compose.verify.yaml stop`. Its volume is retained for inspection. No verification container should be exposed beyond the development machine.
