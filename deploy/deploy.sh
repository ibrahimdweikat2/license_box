#!/usr/bin/env bash
#
# Deploy the latest commit pushed to GitHub to this server (after deploy/install.sh).
#
# Usage (on the server):
#   cd /var/www/licensebox
#   sudo bash deploy/deploy.sh                 # deploy if there are new commits
#   sudo bash deploy/deploy.sh --force         # rebuild even when up to date (e.g. after editing .env)
#   sudo BRANCH=release bash deploy/deploy.sh  # deploy another branch
#
# The site is in maintenance mode during the deploy. Installed apps are not affected: the client
# SDK treats the unsigned 503 like an unreachable server and keeps working in its grace period.
# If something fails before migrations run, the previous commit is restored and the site comes back up.

set -Eeuo pipefail

# The app is the checkout this script lives in; it is owned by the user PHP runs as.
APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
APP_USER="${APP_USER:-$(stat -c %U "$APP_DIR")}"
COMPOSER=/usr/local/bin/composer

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
info() { printf '    %s\n' "$*"; }
warn() { printf '\033[1;33m    WARNING: %s\033[0m\n' "$*"; }
die() { printf '\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }

# Run a command as the app user, from the app directory.
as_app() { sudo -u "$APP_USER" -H bash -c 'cd "$0" && exec "$@"' "$APP_DIR" "$@"; }

STAGE=prepare
PREVIOUS=

build() {
    as_app "$COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    as_app npm ci --no-audit --no-fund
    as_app npm run build
}

on_error() {
    local line=$1
    trap - ERR
    printf '\033[1;31mERROR: deploy failed at line %s (stage: %s).\033[0m\n' "$line" "$STAGE" >&2

    case $STAGE in
        prepare)
            ;;
        build)
            step "Rolling back to ${PREVIOUS:0:7}"
            as_app git reset --hard "$PREVIOUS"
            if build && as_app php artisan optimize; then
                systemctl reload php-fpm
                as_app php artisan up
                info "Rolled back; the site is up on the previous version."
            else
                warn "Rollback failed; the site is still in maintenance mode. Fix it, then: sudo -u $APP_USER php $APP_DIR/artisan up"
            fi
            ;;
        *)
            warn "Migrations may have run, so the code was NOT rolled back automatically."
            warn "The site is still in maintenance mode. Fix the problem and run deploy.sh --force,"
            warn "or bring the site back as it is: sudo -u $APP_USER php $APP_DIR/artisan up"
            ;;
    esac
    exit 1
}

main() {
    [[ $EUID -eq 0 ]] || die "Run as root: sudo bash $0"
    [[ -d $APP_DIR/.git ]] || die "$APP_DIR is not a git checkout."
    [[ -f $APP_DIR/.env && $APP_USER != root ]] || die "Run deploy/install.sh first."
    cd /
    local force=0
    [[ ${1:-} == --force ]] && force=1

    trap 'on_error $LINENO' ERR

    step "Fetching"
    local branch=${BRANCH:-$(as_app git rev-parse --abbrev-ref HEAD)}
    as_app git fetch --prune origin "$branch"
    PREVIOUS=$(as_app git rev-parse HEAD)
    local target
    target=$(as_app git rev-parse "origin/$branch")

    if [[ $PREVIOUS == "$target" && $force -eq 0 ]]; then
        info "Already at ${target:0:7} ($branch). Nothing to deploy; use --force to rebuild anyway."
        exit 0
    fi
    if [[ $PREVIOUS != "$target" ]]; then
        info "Deploying $branch: ${PREVIOUS:0:7} -> ${target:0:7}"
        as_app git log --oneline --no-decorate "$PREVIOUS..$target" | sed 's/^/      /'
    fi

    step "Maintenance mode on"
    as_app php artisan down --retry=60

    STAGE=build
    step "Updating code"
    if [[ $(as_app git rev-parse --abbrev-ref HEAD) != "$branch" ]]; then
        as_app git checkout "$branch"
    fi
    as_app git merge --ff-only "origin/$branch"

    step "Installing dependencies and building assets"
    build

    STAGE=migrate
    step "Migrating the database"
    as_app php artisan migrate --force

    STAGE=finish
    step "Caching config, routes and views"
    as_app php artisan optimize
    systemctl reload php-fpm

    step "Maintenance mode off"
    as_app php artisan up
    trap - ERR

    step "Preflight check"
    if as_app php artisan licensebox:check; then
        step "Deployed $(as_app git rev-parse --short HEAD)"
    else
        warn "Deployed $(as_app git rev-parse --short HEAD) and the site is up, but the check above found problems."
        exit 1
    fi
}

# On one line: bash has read it completely before git replaces this file during the deploy.
main "$@"; exit
