# Upgrading


## Unreleased

## To 1.0.3

From **1.0.2** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/pdf-editor-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## Table of contents

- [From 1.0.1 to 1.0.2](#from-101-to-102)
- [From 1.0.0 to 1.0.1](#from-100-to-101)
- [From nothing → 1.0.0](#from-nothing--100)

## From 1.0.1 to 1.0.2

Worker / `reset_kernel: false` hardening and audit. **No required host changes.**

```bash
composer update nowo-tech/pdf-editor-bundle
```

### Notes

1. Shared services remain stateless; no `kernel.reset` tags are required for this bundle.
2. After `apply`, page PNG lock files are deleted with page images (safer disk hygiene under long-lived FrankenPHP workers).
3. Audit write-up: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md). Demo defaults: `FRANKENPHP_MODE=worker`, `FRANKENPHP_RESET_KERNEL` unset (`false`).
4. Optionally schedule cleanup of `workspace_dir` (workspaces are not auto-purged).

## From 1.0.0 to 1.0.1

Coverage and source restoration. **No required host changes.**

```bash
composer update nowo-tech/pdf-editor-bundle
```

## From nothing → 1.0.0

First public release. No upgrade path.

See [Installation](INSTALLATION.md), [Configuration](CONFIGURATION.md), and [Demo with FrankenPHP](DEMO-FRANKENPHP.md).
