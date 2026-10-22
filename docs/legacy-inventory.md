# Legacy inventory and preservation

Reviewed source: `C:\xampp_8.1\htdocs\SJL\is-tools`. The other applications under `SJL` are independent projects; their links are retained rather than importing their code into IS Tools. Docker/SSL conventions were compared with `C:\Containers\sjl-document-compliance`.

## Forms and permissions

| Legacy page | Laravel page | Access | Original request type |
| --- | --- | --- | --- |
| `forms/ad.php` | `/forms/ad` | Guest | `active directory` |
| `forms/nimis.php` | `/forms/nimis` | Guest | `nimis` |
| `forms/ilab.php` | `/forms/ilab` | Guest | `lab` |
| `forms/drive.php` | `/forms/drive` | Signed in | `network` |
| `forms/assetreq.php` | `/forms/assetreq` | Signed in | `asset request` |
| `forms/software.php` | `/forms/software` | Signed in | `software` |
| `forms/joining.php` | `/forms/joining` | Signed in | `joining` |
| `forms/moving.php` | `/forms/moving` | Signed in | `moving` |
| `forms/leaving.php` | `/forms/leaving` | Signed in | `leaving` |
| `forms/cyber-incident-report.php` | `/forms/cyber-incident-report` | I.S. Staff | `cyber-incident-report` |

The forms retain the legacy fields: contact details and staff identifiers; role, Medical Council and ABA numbers; HealthIRL and professional numbers; requirements and equipment selections; dates; on-behalf-of requests; multiple departing staff; incident details; and signature declarations. The AD department field mismatch (`from` versus `from_dep`) is corrected. All new fields are validated and escaped before storage/rendering.

## Other pages

| Legacy capability | Replacement | Access |
| --- | --- | --- |
| Home, forms, apps, about, wellbeing | `/`, `/forms`, `/apps`, `/about`, `/healthwellbeing` | Public |
| LDAP login, remember me, logout | `/login`, POST `/logout`; old logout page asks for confirmation | Public / signed in |
| Meetings list, create and details | `/meetings`, `/meetings/create`, `/meetings/{id}` | Signed in |
| Meeting report | `/meeting-report`, CSV and print | Signed in, including HR and doctors |
| Request review and processing | `/requests`, `/requests/{id}` | I.S. Staff |
| Own request tracking | `/my-requests` | Signed in; own records only |
| Departments/software and change log | `/data` | I.S. Staff |
| Office 365 shortcuts | `/office365` | I.S. Staff |
| Gotenberg health | Dashboard and `/service-health` | I.S. Staff |
| Email diagnostic | `/deliveries` test-message form | I.S. Staff |
| Directory search | `/directory/search` | Signed in |
| Network folders | Configured host mount, or department options | Signed in |
| Signatures and generated documents | Authorised request/file routes and document archive | Owner where linked / I.S. Staff |

Legacy `.php` page bookmarks redirect to their equivalents. The form POST aliases remain CSRF-protected. Legacy GET approval/rejection URLs cannot mutate records. Old scripts, diagnostic endpoints and raw PHP are not served from `public/`.

## Database

All 13 legacy tables are adopted in place: `data_log`, `departments`, `form_requests`, `ilab_requests`, `login_log`, `meetings`, `meeting_users`, `requests`, `security_groups`, `security_groups_users`, `session_rememberme`, `software`, and `verified_logins`.

`verified_logins.badge_id` remains the account identity and request/group relationship key. Existing numeric IDs and usernames remain unchanged at login; a nullable `display_name` holds the refreshed directory display name. A nullable `remember_token` supports Laravel's encrypted remember cookie. Existing legacy remember-token rows remain in the database but do not authenticate Laravel sessions. Users sign in again after cutover.

New tables: `is_tools_submissions` holds structured new form fields and file paths; `is_tools_deliveries` tracks email delivery; `is_tools_audit` records operational actions; `jobs` and `failed_jobs` support the worker. Historical requests remain readable from their stored messages without requiring structured backfill. Reference-data edits keep writing the existing `data_log`.

## Permissions and intentional behavior changes

- `I.S. Staff` still grants administration. AD memberships are refreshed on successful login, including removal of stale memberships. Users and their historical activity are never deleted by login. No new HR-specific restriction is introduced.
- All signed-in staff can create/view meetings and run reports, matching the legacy code. A presenter can also be an attendee; legacy report totals count these separately. Distinct session count is also shown. Repeated attendee names within one new meeting are saved once.
- iLab checks the existing `ilab_requests` records case-insensitively before accepting a second request. New submissions reserve the name transactionally before notification delivery. This prevents duplicate submission when mail fails.
- Requests and signatures are saved before external notification. Email/PDF failures are visible and retryable. A saved-request message does not claim an email was delivered.
- Completion/rejection requires an authorised CSRF-protected mutation. Completed requests cannot be processed a second time. Asset completion requires an asset tag.
- HTML in old messages is sanitised when displayed. CSV cells beginning with formula characters are escaped. Historical signatures/PDFs are private rather than publicly exposed.
- Passwords are passed unchanged to LDAP and never saved. LDAP failure provides no alternate local login. New remember cookies expire after 30 days.
