#!/usr/bin/env bash
#
# Set up LicenseBox on an Amazon Linux 2023 EC2 server that may already host other PHP sites.
# Run it from inside a git clone of the project:
#
#   sudo git clone https://github.com/YOU/license-box.git /var/www/licensebox
#   cd /var/www/licensebox
#   sudo bash deploy/install.sh
#
# It adds, next to whatever already runs on the server:
#   - Nginx config  /etc/nginx/conf.d/<site>.conf      (other sites' configs are not touched)
#   - PHP-FPM pool  /etc/php-fpm.d/<site>.conf         (own user and upload limits; global php.ini unchanged)
#   - a database + user on the existing MySQL/MariaDB (installs MariaDB only if none exists)
#   - a domain made from the server IP (<site>.<ip>.sslip.io) unless you give a real one, with HTTPS
#   - cron for the Laravel scheduler and nightly backups
# Services are reloaded, never restarted, so the other sites stay up. Safe to run again; to move
# to another domain later, run it again with the new domain.
#
# Answers can be preset as environment variables: SITE_NAME, DOMAIN, LE_EMAIL, BACKUP_S3_URI.
# Updates are deployed with deploy/deploy.sh. See DEPLOYMENT.md.

set -Eeuo pipefail

APP_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
NODE_MAJOR="${NODE_MAJOR:-22}"
COMPOSER=/usr/local/bin/composer

# ---------------------------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------------------------

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
info() { printf '    %s\n' "$*"; }
warn() { printf '\033[1;33m    WARNING: %s\033[0m\n' "$*"; }
die() { printf '\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }

trap 'die "install.sh failed on line $LINENO. Fix the problem and run it again; finished steps are skipped."' ERR

# Run a command as the app user, from the app directory.
as_app() { sudo -u "$APP_USER" -H bash -c 'cd "$0" && exec "$@"' "$APP_DIR" "$@"; }

# ask VAR "Question" [default] [optional]: prompt unless VAR is already set in the environment.
ask() {
    local var=$1 question=$2 default=${3:-} optional=${4:-} answer
    [[ -n ${!var:-} ]] && return
    while true; do
        read -rp "    $question${default:+ [$default]}: " answer
        answer=${answer:-$default}
        [[ -n $answer || -n $optional ]] && break
    done
    printf -v "$var" '%s' "$answer"
}

confirm() {
    local answer
    read -rp "    $1 [y/N]: " answer
    [[ $answer =~ ^[Yy] ]]
}

# set_env KEY VALUE: set a key in the app's .env, adding it when missing.
set_env() {
    local file="$APP_DIR/.env"
    if grep -qE "^#? ?$1=" "$file"; then
        sed -i -E "s|^#? ?$1=.*|$1=$2|" "$file"
    else
        printf '%s=%s\n' "$1" "$2" >> "$file"
    fi
}

get_env() { grep -E "^$1=" "$APP_DIR/.env" 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"' || true; }

public_ip() {
    local token
    token=$(curl -fsS -m 2 -X PUT http://169.254.169.254/latest/api/token -H 'X-aws-ec2-metadata-token-ttl-seconds: 60' 2>/dev/null || true)
    curl -fsS -m 2 -H "X-aws-ec2-metadata-token: $token" http://169.254.169.254/latest/meta-data/public-ipv4 2>/dev/null \
        || curl -fsS -m 5 https://checkip.amazonaws.com 2>/dev/null | tr -d '[:space:]' || true
}

# Can the app user read the repository? Never prompts (no password or host-key questions).
git_access() {
    as_app env GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND='ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new' \
        git ls-remote origin >/dev/null 2>&1
}

# MySQL client as an admin: unix-socket root (MariaDB default) or root with a password (MySQL 8).
db_admin() { mysql ${DB_ADMIN_CNF:+--defaults-extra-file="$DB_ADMIN_CNF"} "$@"; }

# ---------------------------------------------------------------------------------------------
# Steps
# ---------------------------------------------------------------------------------------------

preflight() {
    [[ $EUID -eq 0 ]] || die "Run as root: sudo bash deploy/install.sh"
    # shellcheck disable=SC1091
    . /etc/os-release
    [[ ${ID:-} == amzn && ${VERSION_ID:-} == 2023 ]] || die "This script is written for Amazon Linux 2023 (found: ${PRETTY_NAME:-unknown})."
    [[ -t 0 ]] || die "Run this script from an interactive SSH session (it asks questions)."
    [[ -f $APP_DIR/artisan && -d $APP_DIR/.git ]] || die "Run this from a git clone of the project (expected $APP_DIR/artisan and $APP_DIR/.git)."
    command -v nginx >/dev/null || die "Nginx is not installed. Install it first: sudo dnf install -y nginx"

    step "Settings"
    info "App directory: $APP_DIR"
    ask SITE_NAME "Site name (used for the Nginx config, PHP pool, user and database)" "$(basename "$APP_DIR")"
    SITE_NAME=$(tr '[:upper:]' '[:lower:]' <<< "$SITE_NAME")
    [[ $SITE_NAME =~ ^[a-z][a-z0-9-]{1,30}$ ]] || die "Site name must be 2-31 characters: lowercase letters, digits and dashes, starting with a letter."
    APP_USER=$SITE_NAME
    DB_NAME=${SITE_NAME//-/_}
    DB_USER=$DB_NAME
    FPM_SOCKET=/run/php-fpm/$SITE_NAME.sock
    NGINX_CONF=/etc/nginx/conf.d/$SITE_NAME.conf

    if [[ -f $NGINX_CONF ]] && ! grep -q "Managed by LicenseBox" "$NGINX_CONF"; then
        die "$NGINX_CONF already exists and was not created by this script. Choose another site name."
    fi

    local ip current
    ip=$(public_ip)
    current=$(grep -m1 -oP '^\s*server_name\s+\K[^; ]+' "$NGINX_CONF" 2>/dev/null || true)
    info "Server public IP: ${ip:-unknown}"
    info "Press Enter to use a free domain made from the IP, or type your own domain."
    ask DOMAIN "Domain" "${current:-${ip:+$SITE_NAME.$ip.sslip.io}}"
    DOMAIN=$(tr '[:upper:]' '[:lower:]' <<< "$DOMAIN")
    ask LE_EMAIL "Email for Let's Encrypt certificate notices"
    ask BACKUP_S3_URI "S3 location for off-server backups, e.g. s3://my-bucket/$SITE_NAME (Enter = local only)" "" optional
}

ensure_swap() {
    step "Swap"
    local mem_kb
    mem_kb=$(awk '/MemTotal/ {print $2}' /proc/meminfo)
    if swapon --show | grep -q . || (( mem_kb >= 3000000 )); then
        info "Not needed."
        return
    fi
    # Small instances run out of memory during composer install and the asset build.
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap defaults 0 0' >> /etc/fstab
    info "Added a 2 GB swap file."
}

install_packages() {
    step "System packages"
    # Only installs what is missing; nothing already on the server is upgraded.
    dnf -y install git unzip tar gzip cronie openssl
    systemctl enable --now crond >/dev/null

    local required=(ctype curl dom fileinfo filter mbstring openssl pdo_mysql session tokenizer xml) missing=() ext
    php_missing() {
        missing=()
        command -v php >/dev/null || { missing=(php); return; }
        for ext in "${required[@]}"; do php -m | grep -qix "$ext" || missing+=("$ext"); done
    }

    php_missing
    if (( ${#missing[@]} > 0 )); then
        info "Installing PHP 8.3 packages (missing: ${missing[*]})."
        # Skip any package this AL2023 release does not have; the check below catches real gaps.
        dnf -y install --setopt=strict=False php8.3 php8.3-cli php8.3-common php8.3-fpm php8.3-mysqlnd \
            php8.3-pdo php8.3-mbstring php8.3-xml php8.3-intl php8.3-opcache php8.3-process php8.3-sodium php8.3-bcmath
        php_missing
        (( ${#missing[@]} == 0 )) || die "PHP extensions still missing: ${missing[*]}. Check 'dnf search php8.3'."
    fi
    php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || die "PHP 8.3+ is required; found $(php -r 'echo PHP_VERSION;')."
    systemctl cat php-fpm.service >/dev/null 2>&1 || die "php-fpm.service not found. Install php8.3-fpm."
    php -m | grep -qix 'Zend OPcache' || warn "OPcache is not enabled; PHP will be slower."
    info "$(php -r 'echo "PHP ", PHP_VERSION;'), extensions OK."
}

install_composer() {
    step "Composer"
    if [[ -x $COMPOSER ]]; then
        info "$("$COMPOSER" --version --no-ansi 2>/dev/null | head -n1)"
        return
    fi
    local expected actual
    expected=$(curl -fsSL https://composer.github.io/installer.sig)
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    actual=$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")
    [[ $expected == "$actual" ]] || { rm -f /tmp/composer-setup.php; die "Composer installer checksum mismatch."; }
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
    info "$("$COMPOSER" --version --no-ansi | head -n1)"
}

install_node() {
    step "Node.js (builds the CSS/JS assets)"
    # Vite 8 needs Node 20.19+ or 22.12+.
    local ok='const [a,b]=process.versions.node.split(".").map(Number); process.exit(a>22||(a===22&&b>=12)||(a===20&&b>=19)?0:1)'
    if command -v node >/dev/null; then
        if node -e "$ok"; then
            info "Node $(node -v) already installed."
            return
        fi
        warn "Node $(node -v) is too old for this project (needs 20.19+ or 22.12+)."
        warn "Upgrading it to Node $NODE_MAJOR affects every site on this server that builds with Node."
        confirm "Upgrade Node to $NODE_MAJOR?" || die "Install a newer Node yourself, then run install.sh again."
    fi
    curl -fsSL "https://rpm.nodesource.com/setup_${NODE_MAJOR}.x" -o /tmp/nodesource_setup.sh
    bash /tmp/nodesource_setup.sh >/dev/null
    rm -f /tmp/nodesource_setup.sh
    dnf -y install nodejs
    info "Node $(node -v)"
}

setup_database() {
    step "Database"
    local service= name
    for name in mariadb mysqld mysql; do
        if systemctl cat "$name.service" >/dev/null 2>&1; then service=$name; break; fi
    done

    if [[ -z $service ]]; then
        info "No MySQL/MariaDB found; installing MariaDB (local connections only)."
        dnf -y install mariadb105-server
        cat > /etc/my.cnf.d/licensebox.cnf <<'EOF'
[mysqld]
bind-address = 127.0.0.1
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
EOF
        systemctl enable --now mariadb >/dev/null
        mysql -e "DELETE FROM mysql.global_priv WHERE User=''; DROP DATABASE IF EXISTS test; FLUSH PRIVILEGES;"
    else
        info "Using the existing database server ($service); its configuration is not changed."
        systemctl is-active --quiet "$service" || systemctl start "$service"
    fi

    # Admin access: unix-socket root first, otherwise ask for the root password.
    DB_ADMIN_CNF=
    if ! mysql -e 'SELECT 1' >/dev/null 2>&1; then
        local root_password
        read -rsp "    MySQL root password (needed once to create the database): " root_password; echo
        DB_ADMIN_CNF=$(mktemp)
        chmod 600 "$DB_ADMIN_CNF"
        printf '[client]\nuser=root\npassword="%s"\n' "$root_password" > "$DB_ADMIN_CNF"
        trap 'rm -f "$DB_ADMIN_CNF"' EXIT
        db_admin -e 'SELECT 1' >/dev/null || die "Could not log in to MySQL as root."
    fi

    # Reuse the password from an earlier run so the existing .env keeps working.
    DB_PASSWORD=$(get_env DB_PASSWORD)
    [[ -n $DB_PASSWORD ]] || DB_PASSWORD=$(openssl rand -hex 24)

    db_admin <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    info "Database '$DB_NAME' and user '$DB_USER' ready ($(db_admin -Nse 'SELECT VERSION()'))."
}

setup_user_and_repo() {
    step "App user and repository"
    if id "$APP_USER" >/dev/null 2>&1; then
        info "User '$APP_USER' exists."
    else
        useradd --system --create-home --home-dir "/home/$APP_USER" --shell /bin/bash "$APP_USER"
        info "Created system user '$APP_USER': it owns the code, and this site's PHP runs as it."
    fi
    chown -R "$APP_USER:$APP_USER" "$APP_DIR"
    chmod 755 "$APP_DIR"

    # Nginx must be able to reach public/ (a clone under /home/ec2-user is not reachable).
    sudo -u nginx test -r "$APP_DIR/public/index.php" \
        || die "Nginx cannot read $APP_DIR/public. Clone the project under /var/www instead (e.g. /var/www/$SITE_NAME)."

    # deploy.sh pulls as the app user, so that user needs its own read access to the repository.
    local url
    url=$(as_app git remote get-url origin)
    if git_access; then
        info "Git access OK ($url)."
        return
    fi

    # Private repository: switch origin to SSH and give the app user a read-only deploy key.
    if [[ $url =~ ^https://([^/@]+@)?([^/]+)/(.+)$ ]]; then
        url="git@${BASH_REMATCH[2]}:${BASH_REMATCH[3]}"
        as_app git remote set-url origin "$url"
        info "Switched origin to $url"
    fi
    [[ $url =~ ^(ssh://)?[^@/]+@([^:/]+)[:/] ]] || die "Cannot reach origin ($url) as $APP_USER."
    local key="/home/$APP_USER/.ssh/id_ed25519"
    if [[ ! -f $key ]]; then
        sudo -u "$APP_USER" mkdir -p -m 700 "/home/$APP_USER/.ssh"
        sudo -u "$APP_USER" ssh-keygen -q -t ed25519 -N '' -C "$SITE_NAME@$(hostname)" -f "$key"
    fi
    until git_access; do
        echo
        info "The repository is private. Add this key as a read-only Deploy key:"
        info "GitHub: repository > Settings > Deploy keys > Add deploy key"
        echo
        cat "$key.pub"
        echo
        read -rp "    Press Enter when it is added (Ctrl+C to stop)... " _
    done
    info "Git access OK ($url)."
}

configure_php() {
    step "PHP-FPM pool"
    # A pool of its own: runs as the app user and carries this site's limits, so php.ini
    # and the other sites' pools stay as they are.
    cat > "/etc/php-fpm.d/$SITE_NAME.conf" <<EOF
; Managed by LicenseBox deploy/install.sh
[$SITE_NAME]
user = $APP_USER
group = $APP_USER
listen = $FPM_SOCKET
listen.owner = nginx
listen.group = nginx
listen.mode = 0660
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
catch_workers_output = yes
php_admin_flag[log_errors] = on
php_admin_flag[expose_php] = off
; Release ZIPs are uploaded through the admin panel (Livewire allows up to 500 MB).
php_admin_value[upload_max_filesize] = 512M
php_admin_value[post_max_size] = 520M
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
EOF
    php-fpm -t >/dev/null 2>&1 || { php-fpm -t; rm -f "/etc/php-fpm.d/$SITE_NAME.conf"; die "PHP-FPM rejected the new pool; it was removed again."; }
    if systemctl is-active --quiet php-fpm; then
        systemctl reload php-fpm
    else
        systemctl enable --now php-fpm >/dev/null
    fi
    for _ in {1..10}; do [[ -S $FPM_SOCKET ]] && break; sleep 1; done
    [[ -S $FPM_SOCKET ]] || die "PHP-FPM did not create $FPM_SOCKET."
    info "Pool '$SITE_NAME' listening on $FPM_SOCKET."
}

setup_app() {
    step "Application"
    if [[ ! -f $APP_DIR/.env ]]; then
        sudo -u "$APP_USER" cp "$APP_DIR/.env.production.example" "$APP_DIR/.env"
        info "Created .env from .env.production.example."
    fi
    set_env APP_URL "http://$DOMAIN"
    set_env DB_CONNECTION mysql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 3306
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "$DB_PASSWORD"
    chown "$APP_USER:$APP_USER" "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"

    as_app "$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    [[ -n $(get_env APP_KEY) ]] || as_app php artisan key:generate --force
    as_app npm ci --no-audit --no-fund
    as_app npm run build
    as_app php artisan migrate --force

    echo
    info "Signing keys and the first admin account:"
    as_app php artisan licensebox:install
}

configure_nginx() {
    step "Nginx: $NGINX_CONF"
    if [[ -f $NGINX_CONF ]] && grep -qE "^\s*server_name\s+$DOMAIN;" "$NGINX_CONF" && grep -q 'managed by Certbot' "$NGINX_CONF"; then
        info "Already set up for $DOMAIN with HTTPS; left unchanged."
        return
    fi

    local backup=
    if [[ -f $NGINX_CONF ]]; then
        backup=$(mktemp)
        cp "$NGINX_CONF" "$backup"
        # The domain changed: the old certificate is replaced in setup_https.
        if grep -q 'managed by Certbot' "$NGINX_CONF"; then OLD_CERT=1; fi
    fi

    cat > "$NGINX_CONF" <<EOF
# Managed by LicenseBox deploy/install.sh ($SITE_NAME)
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $APP_DIR/public;
    index index.php;
    charset utf-8;

    # Release uploads (see the PHP-FPM pool /etc/php-fpm.d/$SITE_NAME.conf).
    client_max_body_size 520m;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    access_log /var/log/nginx/$SITE_NAME.access.log;
    error_log  /var/log/nginx/$SITE_NAME.error.log;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|\$) {
        fastcgi_pass unix:$FPM_SOCKET;
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 16k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    # Never leave Nginx broken for the other sites: put the old file back if the test fails.
    if ! nginx -t 2>/dev/null; then
        nginx -t || true
        if [[ -n $backup ]]; then mv "$backup" "$NGINX_CONF"; else rm -f "$NGINX_CONF"; fi
        die "Nginx rejected the new config; the previous state was restored."
    fi
    rm -f "$backup"
    systemctl is-active --quiet nginx && systemctl reload nginx || systemctl enable --now nginx >/dev/null
    info "Serving http://$DOMAIN"
}

setup_https() {
    step "HTTPS (Let's Encrypt)"
    if [[ -f $NGINX_CONF ]] && grep -q 'managed by Certbot' "$NGINX_CONF"; then
        use_scheme https
        info "Already enabled."
        return
    fi

    if ! command -v certbot >/dev/null; then
        # Recent AL2023 releases ship certbot; older ones need it from PyPI.
        if dnf -y install certbot python3-certbot-nginx; then
            systemctl enable --now certbot-renew.timer >/dev/null 2>&1 || true
        else
            dnf -y install python3 augeas-libs
            python3 -m venv /opt/certbot
            /opt/certbot/bin/pip install --quiet --upgrade pip
            /opt/certbot/bin/pip install --quiet certbot certbot-nginx
            ln -sf /opt/certbot/bin/certbot /usr/bin/certbot
            echo "0 3 * * * root /usr/bin/certbot renew --quiet --deploy-hook 'systemctl reload nginx'" > /etc/cron.d/certbot-renew
        fi
    fi

    if [[ ${OLD_CERT:-0} == 1 ]]; then
        certbot delete --cert-name "$SITE_NAME" --non-interactive || true
    fi

    local ip dns_ip
    ip=$(public_ip)
    dns_ip=$(getent ahostsv4 "$DOMAIN" | awk 'NR==1 {print $1}' || true)
    if [[ -n $ip && $dns_ip != "$ip" ]]; then
        warn "$DOMAIN resolves to '${dns_ip:-nothing}', but this server's public IP is $ip."
    fi

    # --cert-name keeps one certificate per site; only this site's server block is changed.
    if certbot --nginx --cert-name "$SITE_NAME" -d "$DOMAIN" -m "$LE_EMAIL" --agree-tos --no-eff-email --redirect --non-interactive; then
        use_scheme https
        info "HTTPS enabled: https://$DOMAIN"
    else
        use_scheme http
        warn "No certificate yet, so the site runs on http://$DOMAIN for now."
        warn "Check that ports 80/443 are open in the security group, then run install.sh again."
    fi
}

# Point APP_URL and the session cookie at the scheme the site is actually served on.
use_scheme() {
    set_env APP_URL "$1://$DOMAIN"
    set_env SESSION_SECURE_COOKIE "$([[ $1 == https ]] && echo true || echo false)"
    as_app php artisan optimize >/dev/null
}

setup_cron() {
    step "Scheduler and backups"
    cat > "/etc/cron.d/$SITE_NAME" <<EOF
# Managed by LicenseBox deploy/install.sh: Laravel scheduler (expires licenses, prunes logs) and nightly backups.
* * * * * $APP_USER cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1
30 2 * * * root BACKUP_DIR=/var/backups/$SITE_NAME BACKUP_S3_URI=$BACKUP_S3_URI bash $APP_DIR/deploy/backup.sh >> /var/log/$SITE_NAME-backup.log 2>&1
EOF
    chmod 644 "/etc/cron.d/$SITE_NAME"
    info "Scheduler runs every minute; backups run daily at 02:30 into /var/backups/$SITE_NAME${BACKUP_S3_URI:+ and $BACKUP_S3_URI}."

    # Take the first backup now, so the signing keys are saved from day one.
    BACKUP_DIR=/var/backups/$SITE_NAME bash "$APP_DIR/deploy/backup.sh" || warn "First backup failed; see the message above."
}

summary() {
    step "Preflight check"
    as_app php artisan licensebox:check || true

    local url
    url=$(get_env APP_URL)
    step "Done"
    info "Site:         $url"
    info "Admin panel:  $url/login"
    info "App:          $APP_DIR (owner $APP_USER), settings in $APP_DIR/.env"
    info "Nginx config: $NGINX_CONF"
    info "Deploy later: cd $APP_DIR && sudo bash deploy/deploy.sh"
    echo
    info "Still to do:"
    info " - Set MAIL_* in .env (password reset emails), then: sudo -u $APP_USER php $APP_DIR/artisan optimize"
    info " - Keep a copy of the signing keys off this server (see DEPLOYMENT.md, 'Backups')."
    [[ -n $BACKUP_S3_URI ]] || info " - Consider BACKUP_S3_URI in /etc/cron.d/$SITE_NAME; local backups die with the instance."
}

main() {
    # Commands run as the app user; git refuses to start in a directory it cannot read (e.g. /home/ec2-user).
    cd /
    preflight
    ensure_swap
    install_packages
    install_composer
    install_node
    setup_database
    setup_user_and_repo
    configure_php
    setup_app
    configure_nginx
    setup_https
    setup_cron
    summary
}

main "$@"; exit
