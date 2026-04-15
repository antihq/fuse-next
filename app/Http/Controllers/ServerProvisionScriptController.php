<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Support\Facades\URL;

class ServerProvisionScriptController extends Controller
{
    public function __invoke(Server $server)
    {
        $publicKey = config('services.server_ssh_public_key');

        if (empty($publicKey)) {
            abort(500, 'Server SSH public key is not configured.');
        }

        $callbackUrl = URL::signedRoute('servers.provision-callback', ['server' => $server]);

        $script = <<<SHELL
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

REPORT_URL='{$callbackUrl}'

reportError() {
    echo "Provisioning failed: \$1"
    curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"error":"'"\$1"'"}' || true
    exit 1
}

trap 'reportError "Script failed at line \$LINENO"' ERR

echo "Setup SSH keys for root"

if [ ! -d /root/.ssh ]; then
    mkdir -p /root/.ssh
    touch /root/.ssh/authorized_keys
fi

cat <<EOF >> /root/.ssh/authorized_keys
{$publicKey}
EOF

echo "Fix root permissions"
chown root:root /root
chown -R root:root /root/.ssh
chmod 700 /root/.ssh
chmod 600 /root/.ssh/authorized_keys

echo "SSH key added successfully"

echo "Notifying Fuse..."
curl -s -X POST "\$REPORT_URL" || true
echo "Done!"
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
