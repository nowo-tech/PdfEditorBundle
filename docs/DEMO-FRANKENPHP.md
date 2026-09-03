# Demo with FrankenPHP (Symfony 8.1)

The Symfony **8.1** demo runs FrankenPHP. Set `FRANKENPHP_MODE=worker` (default) or `classic`. Recreate containers after changing it.

Timeout stack: engine `timeout` 60s < PHP 90s < Caddy write 100s.

```bash
cd demo
make up-symfony8
```

Open the URL printed as `Demo started at: http://localhost:<PORT>`.
