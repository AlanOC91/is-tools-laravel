Place the same deployment certificates used by sjl-document-compliance here:

- `apps.crt`: certificate (with intermediate chain if necessary) valid for `apps`.
- `apps.key`: matching private key.
- `stjohnshospital-CA-CA.crt`: issuing hospital CA in PEM format, trusted at container startup for LDAP StartTLS/LDAPS and SMTP.

These files are mounted read-only and excluded from Git and Docker build context. The private key is never baked into the image. The CA is installed in every application service, including queue workers. Certificate validation remains enabled for LDAP. Restart the services after replacing certificates.
