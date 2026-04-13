<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ServerFullProvisionScriptController extends Controller
{
    public function __invoke(Server $server)
    {
        $publicKey = config('services.server_ssh_public_key');

        if (empty($publicKey)) {
            abort(500, 'Server SSH public key is not configured.');
        }

        $callbackUrl = URL::signedRoute('servers.provision-callback', ['server' => $server]);
        $mysqlPassword = Str::random(32);

        $script = <<<SHELL
#!/bin/bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

REPORT_URL='{$callbackUrl}'

waitForAptUnlock() {
    while ps -C apt,apt-get,dpkg >/dev/null 2>&1; do
        echo "Waiting: apt is running..."
        sleep 5
    done

    while fuser /var/{lib/{dpkg,apt/lists},cache/apt/archives}/{lock,lock-frontend} >/dev/null 2>&1; do
        echo "Waiting: apt is locked..."
        sleep 5
    done

    if [ -f /var/log/unattended-upgrades/unattended-upgrades.log ]; then
        while fuser /var/log/unattended-upgrades/unattended-upgrades.log >/dev/null 2>&1; do
            echo "Waiting: unattended-upgrades is locked..."
            sleep 5
        done
    fi
}

reportError() {
    echo "Provisioning failed: \$1"
    curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"error":"'"\$1"'"}' || true
    exit 1
}

trap 'reportError "Script failed at line \$LINENO"' ERR

echo "=== Starting server provisioning ==="

echo "Configure swap (2GB)"
if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile || true
    echo "/swapfile none swap sw 0 0" >> /etc/fstab
    echo "vm.swappiness=10" >> /etc/sysctl.conf
    echo "vm.vfs_cache_pressure=50" >> /etc/sysctl.conf
fi

echo "Configure firewall (UFW)"
ufw allow 22
ufw allow 80
ufw allow 443
yes | ufw enable
service ufw restart

echo "Update package repositories"
waitForAptUnlock
apt-get update -y

echo "Upgrade packages"
waitForAptUnlock
apt-get upgrade -y

echo "Install essential packages"
waitForAptUnlock
apt-get install -y \\
    acl \\
    apt-transport-https \\
    build-essential \\
    ca-certificates \\
    cron \\
    curl \\
    fail2ban \\
    git \\
    gnupg \\
    htop \\
    jq \\
    libmagickwand-dev \\
    libonig-dev \\
    libpcre3-dev \\
    libpng-dev \\
    libzip-dev \\
    lsb-release \\
    make \\
    nano \\
    ncdu \\
    net-tools \\
    software-properties-common \\
    sudo \\
    supervisor \\
    ufw \\
    unzip \\
    uuid-runtime \\
    vim \\
    wget \\
    zip

echo "Setup unattended security upgrades"
cat > /etc/apt/apt.conf.d/50unattended-upgrades << 'EOF'
Unattended-Upgrade::Allowed-Origins {
    "\${distro_id} \${distro_codename}-security";
};
Unattended-Upgrade::Package-Blacklist {
    //
};
EOF

cat > /etc/apt/apt.conf.d/10periodic << 'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Download-Upgradeable-Packages "1";
APT::Periodic::AutocleanInterval "7";
APT::Periodic::Unattended-Upgrade "1";
EOF

echo "Setup SSH keys for root"
mkdir -p /root/.ssh
touch /root/.ssh/authorized_keys

cat << 'SSHKEY_EOF' >> /root/.ssh/authorized_keys
{$publicKey}
SSHKEY_EOF

chown -R root:root /root/.ssh
chmod 700 /root/.ssh
chmod 600 /root/.ssh/authorized_keys

echo "Enhance SSH security"
sed -i "/PasswordAuthentication yes/d" /etc/ssh/sshd_config
echo "PasswordAuthentication no" | tee -a /etc/ssh/sshd_config
service ssh restart

echo "Add known hosts for Git providers"
ssh-keyscan -H github.com >> /root/.ssh/known_hosts
ssh-keyscan -H bitbucket.org >> /root/.ssh/known_hosts
ssh-keyscan -H gitlab.com >> /root/.ssh/known_hosts

echo "Install Caddy 2 webserver"
waitForAptUnlock
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
waitForAptUnlock
apt-get update
waitForAptUnlock
apt-get install -y caddy=2.*

echo "Configure Caddy"
cat > /etc/caddy/Caddyfile << 'CADDY_EOF'
:80 {
    root * /home/fuse/default
    file_server
}

import /etc/caddy/Sites.caddy
CADDY_EOF

mkdir -p /etc/caddy
touch /etc/caddy/Sites.caddy

echo "Install MySQL 8.0"
waitForAptUnlock
apt-get install -y mysql-server

MYSQL_PASSWORD='{$mysqlPassword}'
mkdir -p /root/.fuse
echo "\$MYSQL_PASSWORD" > /root/.fuse/mysql_password
chmod 600 /root/.fuse/mysql_password

echo "default_password_lifetime = 0" >> /etc/mysql/mysql.conf.d/mysqld.cnf

if grep -q "bind-address" /etc/mysql/mysql.conf.d/mysqld.cnf; then
  sed -i '/^bind-address/s/bind-address.*=.*/bind-address = */' /etc/mysql/mysql.conf.d/mysqld.cnf
else
  echo "bind-address = *" >> /etc/mysql/mysql.conf.d/mysqld.cnf
fi

service mysql start
mysql --user="root" --password="\$MYSQL_PASSWORD" -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '\$MYSQL_PASSWORD';"
mysql --user="root" --password="\$MYSQL_PASSWORD" -e "CREATE USER 'fuse'@'%' IDENTIFIED BY '\$MYSQL_PASSWORD';"
mysql --user="root" --password="\$MYSQL_PASSWORD" -e "CREATE USER 'fuse'@'localhost' IDENTIFIED BY '\$MYSQL_PASSWORD';"
mysql --user="root" --password="\$MYSQL_PASSWORD" -e "GRANT ALL PRIVILEGES ON *.* TO 'fuse'@'%' WITH GRANT OPTION;"
mysql --user="root" --password="\$MYSQL_PASSWORD" -e "GRANT ALL PRIVILEGES ON *.* TO 'fuse'@'localhost' WITH GRANT OPTION;"
mysql --user="root" --password="\$MYSQL_PASSWORD" -e "CREATE DATABASE fuse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --user="root" --password="\$MYSQL_PASSWORD" -e "FLUSH PRIVILEGES;"
service mysql restart

echo "Install Valkey (Redis compatible)"
waitForAptUnlock
curl -fsSL https://packages.valkey.io/gpg | gpg --dearmor -o /usr/share/keyrings/valkey-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/valkey-archive-keyring.gpg] https://packages.valkey.io/valkey/debian \$(lsb_release -cs) main" > /etc/apt/sources.list.d/valkey.list
waitForAptUnlock
apt-get update
waitForAptUnlock
apt-get install -y valkey-server
sed -i 's/bind 127.0.0.1/bind 0.0.0.0/' /etc/valkey/valkey.conf
systemctl enable valkey-server
systemctl restart valkey-server

echo "Install PHP 8.2, 8.3, 8.4, 8.5"
waitForAptUnlock
apt-add-repository ppa:ondrej/php -y
apt-get update
waitForAptUnlock

for version in 8.2 8.3 8.4 8.5; do
    echo "Installing PHP \$version"
    waitForAptUnlock
    apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y --force-yes \\
        php\$version-bcmath \\
        php\$version-cli \\
        php\$version-curl \\
        php\$version-fpm \\
        php\$version-gd \\
        php\$version-gmp \\
        php\$version-imagick \\
        php\$version-intl \\
        php\$version-mbstring \\
        php\$version-mysql \\
        php\$version-readline \\
        php\$version-soap \\
        php\$version-sqlite3 \\
        php\$version-xml \\
        php\$version-zip \\
        php\$version-redis

    sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/\$version/fpm/php.ini
    sed -i "s/display_errors = .*/display_errors = Off/" /etc/php/\$version/fpm/php.ini
    sed -i "s/memory_limit = .*/memory_limit = 512M/" /etc/php/\$version/fpm/php.ini
    sed -i "s/;date.timezone.*/date.timezone = UTC/" /etc/php/\$version/fpm/php.ini
    sed -i "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/" /etc/php/\$version/fpm/php.ini
    sed -i "s/;request_terminate_timeout.*/request_terminate_timeout = 60/" /etc/php/\$version/fpm/pool.d/www.conf
    sed -i "s/^user = www-data/user = fuse/" /etc/php/\$version/fpm/pool.d/www.conf
    sed -i "s/^group = www-data/group = fuse/" /etc/php/\$version/fpm/pool.d/www.conf
    sed -i "s/;listen\.owner.*/listen.owner = fuse/" /etc/php/\$version/fpm/pool.d/www.conf
    sed -i "s/;listen\.group.*/listen.group = fuse/" /etc/php/\$version/fpm/pool.d/www.conf
    sed -i "s/;listen\.mode.*/listen.mode = 0666/" /etc/php/\$version/fpm/pool.d/www.conf

    service php\$version-fpm restart > /dev/null 2>&1
done

echo "Set PHP 8.5 as default"
update-alternatives --set php /usr/bin/php8.5
update-alternatives --set phar /usr/bin/phar8.5

echo "Install Composer 2"
curl -sS https://getcomposer.org/installer | php -- --2
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

echo "Install Node.js 22 LTS"
waitForAptUnlock
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get update
waitForAptUnlock
apt-get install -y nodejs

echo "Create fuse user"
if getent passwd 1000 > /dev/null 2>&1; then
    echo "Renaming existing user 1000"
    OLD_USERNAME=\$(getent passwd 1000 | cut -d: -f1)
    (pkill -9 -u \$OLD_USERNAME || true)
    usermod --login fuse --move-home --home /home/fuse \$OLD_USERNAME 2>/dev/null || true
    groupmod --new-name fuse \$OLD_USERNAME 2>/dev/null || true
else
    useradd -m fuse
fi

mkdir -p /home/fuse/.ssh
mkdir -p /home/fuse/default

adduser fuse sudo
chsh -s /bin/bash fuse

cp /root/.bashrc /home/fuse/.bashrc 2>/dev/null || true
cp /root/.profile /home/fuse/.profile 2>/dev/null || true

cp /root/.ssh/authorized_keys /home/fuse/.ssh/authorized_keys
cp /root/.ssh/known_hosts /home/fuse/.ssh/known_hosts
ssh-keygen -t rsa -N '' -f /home/fuse/.ssh/id_rsa 2>/dev/null || true

chown -R fuse:fuse /home/fuse
chmod -R 755 /home/fuse
chmod 700 /home/fuse/.ssh
chmod 600 /home/fuse/.ssh/authorized_keys
chmod 600 /home/fuse/.ssh/id_rsa

echo "Update Caddy to run as fuse"
service caddy stop
mkdir -p /etc/systemd/system/caddy.service.d
cat > /etc/systemd/system/caddy.service.d/override.conf << 'OVERRIDE_EOF'
[Service]
User=fuse
Group=fuse
OVERRIDE_EOF

systemctl daemon-reload
service caddy start

echo "fuse ALL=(root) NOPASSWD: /usr/sbin/service caddy reload" >> /etc/sudoers.d/caddy
echo "fuse ALL=(root) NOPASSWD: /usr/sbin/service php*-fpm reload" >> /etc/sudoers.d/php-fpm

echo "Configure Supervisor"
systemctl enable supervisor
systemctl start supervisor

echo "Create default page"
cat > /home/fuse/default/index.html << 'INDEX_EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Server Provisioned</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f3f4f6; }
        .container { text-align: center; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { margin: 0 0 0.5rem 0; color: #1f2937; }
        p { margin: 0; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Server Provisioned</h1>
        <p>This server is managed by Fuse.</p>
    </div>
</body>
</html>
INDEX_EOF

chown -R fuse:fuse /home/fuse/default

echo "=== Provisioning complete ==="
curl -s -X POST "\$REPORT_URL" -H 'Content-Type: application/json' -d '{"status":"completed"}' || true
echo "Done!"
SHELL;

        return response($script, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
