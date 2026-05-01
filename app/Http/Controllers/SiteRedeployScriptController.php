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
PHP='/usr/bin/php{$site->php_version}'
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
\$PHP artisan down

echo "Install Composer dependencies"
\$PHP /usr/local/bin/composer install --optimize-autoloader --no-dev --no-interaction

echo "Build frontend assets"
npm install && npm run build

echo "Run database migrations"
\$PHP artisan migrate --force

echo "Create storage link"
\$PHP artisan storage:link --force

echo "Cache Laravel configuration"
\$PHP artisan config:cache
\$PHP artisan route:cache
\$PHP artisan view:cache

echo "Set directory permissions"
chmod -R 775 storage bootstrap/cache database
chown -R fuse:fuse storage bootstrap/cache database

echo "Reload PHP-FPM"
touch /tmp/fpmlock 2>/dev/null || true
( flock -w 10 9 || exit 1
    sudo service php{$site->php_version}-fpm reload ) 9>/tmp/fpmlock
SHELL;

        if ($site->queue_enabled) {
            $script .= <<<SHELL

echo "Restart queue supervisor"
sudo supervisorctl restart {$site->domain}-worker:*
SHELL;
        }

        $script .= <<<'SHELL'

echo "Bring application back up"
$PHP artisan up

echo "Run health check"
curl -sf -o /dev/null https://$DOMAIN/up || curl -sf -o /dev/null https://$DOMAIN || reportError "Health check failed"

echo "=== Redeployment completed successfully ==="
curl -s -X POST "$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"deployed"}' || true
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
