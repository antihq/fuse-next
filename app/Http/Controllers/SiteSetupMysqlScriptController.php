<?php

namespace App\Http\Controllers;

use App\Models\Site;

class SiteSetupMysqlScriptController extends Controller
{
    public function __invoke(Site $site)
    {
        $callbackUrl = url()->signedRoute('sites.deploy-callback', ['site' => $site]);

        $dbName = 'site_' . $site->id;
        $dbUser = 'site_' . $site->id;

        $script = <<<SHELL
#!/bin/bash
set -euo pipefail

REPORT_URL='{$callbackUrl}'
DOMAIN='{$site->domain}'
DB_NAME='{$dbName}'
DB_USER='{$dbUser}'
DEPLOY_DIR="/home/fuse/\$DOMAIN"

reportError() {
    echo "MySQL setup failed: \$1"
    curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"error":"'"\$1"'"}' || true
    exit 1
}

trap 'reportError "Script failed at line \$LINENO"' ERR

echo "=== Starting MySQL setup for site: \$DOMAIN ==="

if [ ! -f /home/fuse/.my.cnf ]; then
    reportError "MySQL not available on this server (~/.my.cnf not found)"
fi

echo "Generate MySQL password for site database"
DB_PASSWORD=\$(openssl rand -hex 16)

echo "Create MySQL database and user"
mysql -e "CREATE DATABASE IF NOT EXISTS \$DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER '\$DB_USER'@'127.0.0.1' IDENTIFIED BY '\$DB_PASSWORD';"
mysql -e "GRANT ALL PRIVILEGES ON \$DB_NAME.* TO '\$DB_USER'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"

echo "Update .env with MySQL credentials"
cd "\$DEPLOY_DIR"

if [ ! -f .env ]; then
    reportError ".env file not found in \$DEPLOY_DIR"
fi

sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env || echo "DB_CONNECTION=mysql" >> .env
sed -i 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' .env || echo "DB_HOST=127.0.0.1" >> .env
sed -i 's/^DB_PORT=.*/DB_PORT=3306/' .env || echo "DB_PORT=3306" >> .env
sed -i "s/^DB_DATABASE=.*/DB_DATABASE=\$DB_NAME/" .env || echo "DB_DATABASE=\$DB_NAME" >> .env
sed -i "s/^DB_USERNAME=.*/DB_USERNAME=\$DB_USER/" .env || echo "DB_USERNAME=\$DB_USER" >> .env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=\$DB_PASSWORD/" .env || echo "DB_PASSWORD=\$DB_PASSWORD" >> .env

sed -i '/^DB_SQLITE/d' .env

echo "Remove SQLite database if present"
rm -f "\$DEPLOY_DIR/database/database.sqlite"

echo "Run database migrations against MySQL"
php artisan migrate --force

echo "Cache Laravel configuration"
php artisan config:cache

echo "=== MySQL setup completed successfully ==="
curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"deployed","mysql_database":"\$DB_NAME"}' || true
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
