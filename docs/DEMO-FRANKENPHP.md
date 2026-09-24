# Demo with FrankenPHP (Symfony 8)

The Symfony **8** demo runs FrankenPHP. Set `FRANKENPHP_MODE=worker` (default) or `classic`. Recreate containers after changing it.

## Runtime modes

| Variable | Default | Behaviour |
| --- | --- | --- |
| `FRANKENPHP_MODE` | `worker` | Entrypoint keeps `Caddyfile` with `php_server { worker … }` |
| `FRANKENPHP_MODE=classic` | — | Entrypoint copies `Caddyfile.dev` (one PHP process per request) |
| `FRANKENPHP_RESET_KERNEL` | unset → **false** | Symfony Runtime does **not** reboot the kernel between worker requests (scenario B in the [worker audit](FRANKENPHP-WORKER-AUDIT.md)) |

This bundle is **100% compatible** with worker mode and `reset_kernel: false`. No bundle-specific reset hooks are required.

Timeout stack: engine `timeout` 60s < PHP 90s < Caddy write 100s.

```bash
cd demo
make up-symfony8
```

Open the URL printed as `Demo started at: http://localhost:<PORT>`.

After changing `.env` / `FRANKENPHP_MODE`, recreate the container (`docker compose up -d`); a plain restart does not reload env.
