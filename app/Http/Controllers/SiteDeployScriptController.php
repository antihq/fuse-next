<?php

namespace App\Http\Controllers;

use App\Models\Site;

class SiteDeployScriptController extends Controller
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
\$PHP /usr/local/bin/composer install --optimize-autoloader --no-dev --no-interaction

echo "Copy .env.example to .env"
if [ -f .env.example ]; then
    cp .env.example .env
else
    echo "APP_KEY=" > .env
fi

echo "Set APP_ENV=production and APP_DEBUG=false"
sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env || echo "APP_ENV=production" >> .env
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env || echo "APP_DEBUG=false" >> .env
sed -i "s/^APP_URL=.*/APP_URL=https:\/\/\$DOMAIN/" .env || echo "APP_URL=https://\$DOMAIN" >> .env

echo "Force SQLite database connection"
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env || echo "DB_CONNECTION=sqlite" >> .env
sed -i '/^DB_HOST=/d' .env
sed -i '/^DB_PORT=/d' .env
sed -i '/^DB_DATABASE=/d' .env
sed -i '/^DB_USERNAME=/d' .env
sed -i '/^DB_PASSWORD=/d' .env

echo "Create SQLite database"
mkdir -p database
touch database/database.sqlite

echo "Generate APP_KEY"
\$PHP artisan key:generate --ansi

echo "Run database migrations"
\$PHP artisan migrate --force

echo "Build frontend assets"
npm install && npm run build

echo "Create storage link"
\$PHP artisan storage:link

echo "Cache Laravel configuration"
\$PHP artisan config:cache
\$PHP artisan route:cache
\$PHP artisan view:cache

echo "Set directory permissions"
sudo chmod -R 775 "\$DEPLOY_DIR/storage" "\$DEPLOY_DIR/bootstrap/cache" "\$DEPLOY_DIR/database"
sudo chown -R fuse:fuse "\$DEPLOY_DIR/storage" "\$DEPLOY_DIR/bootstrap/cache" "\$DEPLOY_DIR/database"

echo "Generate Caddy configuration"
CADDY_CONFIG="/etc/caddy/sites.caddy"

cat >> "\$CADDY_CONFIG" << CADDY_EOF

{$site->domain} {
    root * /home/fuse/{$site->domain}/public
    file_server
    php_fastcgi unix//var/run/php/php{$site->php_version}-fpm.sock

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

echo "Reload PHP-FPM"
touch /tmp/fpmlock 2>/dev/null || true
( flock -w 10 9 || exit 1
    sudo service php{$site->php_version}-fpm reload ) 9>/tmp/fpmlock
SHELL;

        if ($site->queue_enabled) {
            $script .= <<<SHELL

echo "Configure queue supervisor"
SUPERVISOR_CONF="/etc/supervisor/conf.d/\${DOMAIN}-worker.conf"
sudo tee "\$SUPERVISOR_CONF" > /dev/null << 'SUPERVISOR_EOF'
[program:{$site->domain}-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php{$site->php_version} /home/fuse/{$site->domain}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=fuse
numprocs=1
redirect_stderr=true
stdout_logfile=/home/fuse/{$site->domain}/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR_EOF

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start {$site->domain}-worker:*
SHELL;
        }

        $script .= <<<'SHELL'

echo "Run health check"
for i in $(seq 1 30); do
    if curl -sf -o /dev/null https://$DOMAIN/up || curl -sf -o /dev/null https://$DOMAIN; then
        echo "Health check passed"
        break
    fi
    if [ $i -eq 30 ]; then
        reportError "Health check failed after 30 attempts"
    fi
    echo "Health check attempt $i failed, retrying in 2s..."
    sleep 2
done

echo "=== Deployment completed successfully ==="
curl -s -X POST "$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"deployed"}' || true
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
