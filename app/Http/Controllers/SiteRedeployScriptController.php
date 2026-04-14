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

echo "Install Composer dependencies"
composer install --optimize-autoloader --no-dev --no-interaction

echo "Set directory permissions"
chmod -R 775 storage bootstrap/cache
chown -R fuse:fuse storage bootstrap/cache

echo "=== Redeployment completed successfully ==="
curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"deployed"}' || true
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
