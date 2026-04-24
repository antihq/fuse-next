<?php

namespace App\Http\Controllers;

use App\Models\Site;

class SiteDestroyScriptController extends Controller
{
    public function __invoke(Site $site)
    {
        $callbackUrl = url()->signedRoute('sites.destroy-callback', ['site' => $site]);

        $mysqlCleanup = '';
        $dbUser = 'site_' . $site->id;

        if ($site->mysql_database) {
            $mysqlCleanup = <<<SHELL

echo "Drop MySQL database and user"
mysql -e "DROP DATABASE IF EXISTS {$site->mysql_database};"
mysql -e "DROP USER IF EXISTS '{$dbUser}'@'127.0.0.1';"
SHELL;
        }

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

echo "Restart PHP-FPM"
sudo service php8.5-fpm restart
{$mysqlCleanup}
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
