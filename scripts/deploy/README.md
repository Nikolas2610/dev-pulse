# DevPulse VPS deployment

The production workflow runs when a pull request is merged into `main`. It connects to the VPS, resets the production checkout to `origin/main`, and runs `vps-deploy.sh`.

Configure these secrets in the GitHub `production` environment:

## Required

- `VPS_HOST`
- `VPS_PORT` (normally `22`)
- `VPS_USER`
- `VPS_SSH_KEY`
- `VPS_APP_DIR` (for example, `/var/www/dev-pulse`)

## Recommended

- `VPS_HOST_FINGERPRINT` — the SSH host key fingerprint.
- `VPS_PHP_BIN` — defaults to `php`.
- `VPS_COMPOSER_BIN` — defaults to `composer`.
- `VPS_NODE_BIN` — defaults to `node`.
- `VPS_NPM_BIN` — defaults to `npm`.
- `VPS_PHP_FPM_RELOAD_COMMAND` — defaults to `sudo systemctl reload php8.3-fpm`.
- `VPS_QUEUE_RESTART_COMMAND` — optional Supervisor/systemd restart command after `artisan queue:restart`.
- `VPS_WRITABLE_PATHS_COMMAND` — defaults to setting `storage` and `bootstrap/cache` ownership to `www-data` with mode `775`.
- `VPS_HEALTHCHECK_URL` — optional URL checked after the app leaves maintenance mode, such as `https://devpulse.example.com/up`.
- `VPS_SKIP_NPM_BUILD` — set to `true` only when assets are built and deployed separately.

The deploy user needs repository access plus permission to run any configured `sudo` commands without an interactive password prompt.
