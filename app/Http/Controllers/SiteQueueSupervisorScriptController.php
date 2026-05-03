<?php

namespace App\Http\Controllers;

use App\Models\Site;

class SiteQueueSupervisorScriptController extends Controller
{
    public function __invoke(Site $site)
    {
        if ($site->queue_enabled) {
            return $this->setupScript($site->domain, $site->php_version);
        }

        return $this->teardownScript($site->domain);
    }

    private function setupScript(string $domain, string $phpVersion)
    {
        $script = <<<SHELL
#!/bin/bash
set -euo pipefail

DOMAIN='{$domain}'
PHP='/usr/bin/php{$phpVersion}'
DEPLOY_DIR="/home/fuse/\$DOMAIN"

echo "=== Setting up queue supervisor for \$DOMAIN ==="

SUPERVISOR_CONF="/etc/supervisor/conf.d/\${DOMAIN}-worker.conf"

echo "Create supervisor configuration"
sudo tee "\$SUPERVISOR_CONF" > /dev/null << 'EOF'
[program:{$domain}-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php{$phpVersion} /home/fuse/{$domain}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=fuse
numprocs=1
redirect_stderr=true
stdout_logfile=/home/fuse/{$domain}/storage/logs/worker.log
stopwaitsecs=3600
EOF

echo "Ensure log directory and worker log exist"
mkdir -p "\$DEPLOY_DIR/storage/logs"
touch "\$DEPLOY_DIR/storage/logs/worker.log"
chmod -R 775 "\$DEPLOY_DIR/storage/logs"

echo "Reload supervisor"
sudo supervisorctl reread
sudo supervisorctl update

echo "Start queue worker"
sudo supervisorctl start {$domain}-worker:*

echo "=== Queue supervisor setup completed ==="
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }

    private function teardownScript(string $domain)
    {
        $script = <<<SHELL
#!/bin/bash
set -euo pipefail

DOMAIN='{$domain}'

echo "=== Removing queue supervisor for \$DOMAIN ==="

SUPERVISOR_CONF="/etc/supervisor/conf.d/\${DOMAIN}-worker.conf"

echo "Stop queue worker"
sudo supervisorctl stop {$domain}-worker:* 2>/dev/null || true

echo "Remove supervisor configuration"
if [ -f "\$SUPERVISOR_CONF" ]; then
    sudo rm "\$SUPERVISOR_CONF"
    echo "Configuration removed"
else
    echo "No configuration found, skipping"
fi

echo "Reload supervisor"
sudo supervisorctl reread
sudo supervisorctl update

echo "=== Queue supervisor removed ==="
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
