# Demo with FrankenPHP (Symfony 8)

The Symfony **8** demo runs FrankenPHP. Set `FRANKENPHP_MODE=worker` (default) or `classic`. Recreate containers after changing it.

## Table of contents

- [Runtime modes](#runtime-modes)
- [Development](#development)
- [Production](#production)
- [Troubleshooting](#troubleshooting)

## Runtime modes

| Variable | Default | Behaviour |
| --- | --- | --- |
| `FRANKENPHP_MODE` | `worker` | Entrypoint keeps `Caddyfile` with `php_server { worker … }` |
| `FRANKENPHP_MODE=classic` | — | Entrypoint copies `Caddyfile.dev` (one PHP process per request) |
| `FRANKENPHP_RESET_KERNEL` | unset → **false** | Symfony Runtime does **not** reboot the kernel between worker requests (scenario B in the [worker audit](FRANKENPHP-WORKER-AUDIT.md)) |

This bundle is **100% compatible** with worker mode and `reset_kernel: false`. No bundle-specific reset hooks are required.

Timeout stack: engine `timeout` 60s < PHP 90s < Caddy write 100s, with `max_wait_time 30s`; both demo Caddyfiles set it (`servers { timeouts { write 100s } }` and `frankenphp { php_ini max_execution_time 90; php_ini max_input_time 90; max_wait_time 30s }`).

```bash
cd demo
make up-symfony8
```

Open the URL printed as `Demo started at: http://localhost:<PORT>`.

After changing `.env` / `FRANKENPHP_MODE`, recreate the container (`docker compose up -d`); a plain restart does not reload env.

## Development

- `make -C demo up-symfony8` (or `cd demo/symfony8 && make up`) builds the image, copies `.env.example` to `.env` when missing and starts FrankenPHP with `APP_ENV=dev`.
- `FRANKENPHP_MODE=classic` in `demo/symfony8/.env` selects `docker/frankenphp/Caddyfile.dev` (one PHP process per request) so PHP and Twig edits show on refresh; `docker/php-dev.ini` disables opcache revalidation delays.
- `make -C demo/symfony8 update-bundle` refreshes the mounted bundle (autoload + cache) after editing `src/`; `make -C demo/symfony8 assets` rebuilds the React editor.

## Production

The demo is a development stack. To run the same image as production:

1. Set `APP_ENV=prod`, `APP_DEBUG=0` and a real `APP_SECRET` (environment of the `php` service, not `.env.example`).
2. Keep `FRANKENPHP_MODE=worker` (default): `docker/frankenphp/Caddyfile` runs `php_server { worker … }`.
3. Build the editor assets (`pnpm run build`) and run `php bin/console cache:warmup` and `assets:install` before serving.
4. Keep the timeout hierarchy above (engine `timeout` < PHP `max_execution_time` < Caddy write timeout) when the Python engine is enabled.

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| PHP or Twig changes do not show on refresh | Worker mode keeps the kernel in memory | Use `FRANKENPHP_MODE=classic`, or `make -C demo/symfony8 restart` after edits |
| Changing `FRANKENPHP_MODE` has no effect | `docker compose restart` does not reload `.env` | `docker compose up -d` (recreates the container) |
| Editor page is blank / `pdf-editor.js` 404 | Assets not built or not installed | `make -C demo/symfony8 assets` |
| Python engine errors (`engine_mode: python`) | PyMuPDF missing or engine path wrong | `php bin/console nowo:pdf-editor:check-engine` inside the container |
| Save fails on large files | Upload or engine limits | Raise `max_upload_bytes` / engine `timeout` within the timeout hierarchy |
| Port already in use | Another stack uses `PORT` (default 8031) | Change `PORT` in `demo/symfony8/.env` |
