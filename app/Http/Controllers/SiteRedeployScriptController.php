<?php

namespace App\Http\Controllers;

use App\Models\Site;

class SiteRedeployScriptController extends Controller
{
    public function __invoke(Site $site)
    {
        $callbackUrl = url()->signedRoute('sites.deploy-callback', ['site' => $site]);

        $script = <<<SHELL
#!/bin/bash
set -euo pipefail

REPORT_URL='{$callbackUrl}'
DOMAIN='{$site->domain}'
REPOSITORY='{$site->repository}'
DEPLOY_DIR="/home/fuse/\$DOMAIN"

reportError() {
    echo "Deployment failed: \$1"
    curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"error":"'"\$1"'"}' || true
    exit 1
}

trap 'reportError "Script failed at line \$LINENO"' ERR

echo "=== Starting site redeployment: \$DOMAIN ==="

echo "Update site status to deploying"
curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"deploying"}' || true

echo "Pulling latest changes from repository"
cd "\$DEPLOY_DIR"
git pull "\$REPOSITORY"

echo "Put application in maintenance mode"
php artisan down

echo "Install Composer dependencies"
composer install --optimize-autoloader --no-dev --no-interaction

echo "Build frontend assets"
npm install && npm run build

echo "Run database migrations"
php artisan migrate --force

echo "Create storage link"
php artisan storage:link

echo "Cache Laravel configuration"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Set directory permissions"
chmod -R 775 storage bootstrap/cache
chown -R fuse:fuse storage bootstrap/cache

echo "Restart PHP-FPM"
sudo service php8.5-fpm restart

echo "Bring application back up"
php artisan up

echo "Run health check"
curl -f https://\$DOMAIN/up || reportError "Health check failed"

echo "=== Redeployment completed successfully ==="
curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"deployed"}' || true
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
