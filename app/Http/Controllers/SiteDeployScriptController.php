<?php

namespace App\Http\Controllers;

use App\Models\Site;

class SiteDeployScriptController extends Controller
{
    public function __invoke(Site $site)
    {
        $server = $site->server;
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

echo "=== Starting site deployment: \$DOMAIN ==="

echo "Update site status to deploying"
curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"deploying"}' || true

echo "Clone repository to \$DEPLOY_DIR"
if [ -d "\$DEPLOY_DIR" ]; then
    echo "Directory exists, removing..."
    rm -rf "\$DEPLOY_DIR"
fi

git clone "\$REPOSITORY" "\$DEPLOY_DIR"
cd "\$DEPLOY_DIR"

echo "Install Composer dependencies"
composer install --optimize-autoloader --no-dev --no-interaction

echo "Copy .env.example to .env"
if [ -f .env.example ]; then
    cp .env.example .env
else
    echo "APP_KEY=" > .env
fi

echo "Generate APP_KEY"
php artisan key:generate --ansi

echo "Set directory permissions"
chmod -R 775 storage bootstrap/cache
chown -R fuse:fuse storage bootstrap/cache

echo "Generate Caddy configuration"
CADDY_CONFIG="/etc/caddy/sites.caddy"

cat >> "\$CADDY_CONFIG" << CADDY_EOF

{$site->domain} {
    root * /home/fuse/{$site->domain}/public
    file_server
    php_fastcgi unix//var/run/php/php8.5-fpm.sock

    encode gzip

    @static {
        file
        path *.ico *.css *.js *.png *.jpg *.jpeg *.gif *.svg *.woff *.woff2 *.ttf *.eot
    }
    header @static Cache-Control "public, max-age=31536000, immutable"
}
CADDY_EOF

echo "Reload Caddy"
sudo service caddy reload

echo "=== Deployment completed successfully ==="
curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"deployed"}' || true
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
