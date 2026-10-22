#!/bin/sh
set -eu
if [ ! -f /var/www/html/.env ]; then
    echo 'Mount the configured .env at /var/www/html/.env.' >&2
    exit 1
fi
# Install the same hospital CA used by the document compliance project.
if [ -s /etc/apache2/ssl/stjohnshospital-CA-CA.crt ]; then
    cp /etc/apache2/ssl/stjohnshospital-CA-CA.crt /usr/local/share/ca-certificates/stjohnshospital-CA-CA.crt
    update-ca-certificates >/dev/null
fi
mkdir -p bootstrap/cache documents storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data bootstrap/cache documents storage
if [ "$#" -eq 0 ]; then set -- apache2-foreground; fi
if [ "$1" = 'apache2-foreground' ]; then
    if [ ! -s /etc/apache2/ssl/apps.crt ] || [ ! -s /etc/apache2/ssl/apps.key ]; then
        echo 'Place apps.crt and apps.key in the repository ssl directory.' >&2
        exit 1
    fi
    exec "$@"
fi
exec gosu www-data "$@"
