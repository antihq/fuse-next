<?php

namespace App\Http\Controllers;

use App\Models\Site;

class SiteDestroyScriptController extends Controller
{
    public function __invoke(Site $site)
    {
        $callbackUrl = url()->signedRoute('sites.destroy-callback', ['site' => $site]);

        $script = <<<SHELL
#!/bin/bash
set -euo pipefail

REPORT_URL='{$callbackUrl}'
DOMAIN='{$site->domain}'
DEPLOY_DIR="/home/fuse/\$DOMAIN"

reportError() {
    echo "Site removal failed: \$1"
    curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"error":"'"\$1"'"}' || true
    exit 1
}

trap 'reportError "Script failed at line \$LINENO"' ERR

echo "=== Starting site removal: \$DOMAIN ==="
SHELL;

        if ($site->queue_enabled) {
            $script .= <<<SHELL

echo "Stop queue supervisor"
sudo supervisorctl stop {$site->domain}-worker:* 2>/dev/null || true

echo "Remove supervisor configuration"
SUPERVISOR_CONF="/etc/supervisor/conf.d/\${DOMAIN}-worker.conf"
if [ -f "\$SUPERVISOR_CONF" ]; then
    sudo rm "\$SUPERVISOR_CONF"
    sudo supervisorctl reread
    sudo supervisorctl update
    echo "Supervisor configuration removed"
else
    echo "No supervisor configuration found, skipping"
fi
SHELL;
        }

        $script .= <<<SHELL

echo "Remove Caddy configuration for \$DOMAIN"
CADDY_CONFIG="/etc/caddy/sites.caddy"

if [ -f "\$CADDY_CONFIG" ]; then
    sed -i '/^{$site->domain} {/,/^}/d' "\$CADDY_CONFIG"
    sed -i '/^\s*$/d' "\$CADDY_CONFIG"
    echo "Removed Caddy config block"
else
    echo "No Caddy config file found, skipping"
fi

echo "Reload Caddy"
sudo service caddy reload

echo "Reload PHP-FPM"
touch /tmp/fpmlock 2>/dev/null || true
( flock -w 10 9 || exit 1
    sudo service php{$site->php_version}-fpm reload ) 9>/tmp/fpmlock

echo "Remove deploy directory \$DEPLOY_DIR"
if [ -d "\$DEPLOY_DIR" ]; then
    rm -rf "\$DEPLOY_DIR"
    echo "Directory removed"
else
    echo "Directory not found, skipping"
fi

echo "=== Site removal completed successfully ==="
curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"destroyed"}' || true
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
