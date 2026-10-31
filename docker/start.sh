#!/bin/sh
set -e

# Render mounts secret files at /etc/secrets. Copying them gives Apache's user access,
# keeps them outside public/, and stops start-up with a clear error if one is missing.
cp /etc/secrets/config.php /etc/secrets/ca.pem /var/www/app/
chown www-data:www-data /var/www/app/config.php /var/www/app/ca.pem
chmod 640 /var/www/app/config.php /var/www/app/ca.pem

# Render chooses the port.
echo "Listen ${PORT:-10000}" > /etc/apache2/ports.conf

exec apache2-foreground
