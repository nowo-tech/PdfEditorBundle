# Upgrading

## Unreleased

## To 1.2.0

From **1.1.0** — workspace purge command, unused profiles deprecated, full test coverage.

```bash
composer update nowo-tech/pdf-editor-bundle
```

- **Single profile:** only `default_profile` is used. If your config defines more than one entry under `nowo_pdf_editor.profiles`, remove the unused ones (they never had an effect and now trigger a deprecation).
- **Workspace cleanup:** schedule `php bin/console nowo:pdf-editor:purge --older-than=24h` (cron or Symfony Scheduler). Existing workspaces older than the given age are deleted the first time it runs; try `--dry-run` first.

## To 1.1.0

From **1.0.5** — security - workspaces bound to their owner, PDF magic check, safe view headers, complete translations.

```bash
composer update nowo-tech/pdf-editor-bundle
```

- A workspace can only be opened by the user/session that created it; others get 404. Workspaces created before the upgrade (or from the CLI) have no owner and are only reachable by callers with no identity.
- Uploads must start with the `%PDF-` signature (a `.pdf` extension is not enough).
- `/view` now sends `X-Content-Type-Options: nosniff` and `Content-Security-Policy: sandbox`.
- `symfony/framework-bundle` and `symfony/asset` are now runtime requirements.

## To 1.0.5

From **1.0.4** — dependency updates only.

```bash
composer update nowo-tech/pdf-editor-bundle
```

No breaking changes. **No application upgrade steps.**

## To 1.0.4

From **1.0.3** — upload size + workspace-bound images.

```bash
composer update nowo-tech/pdf-editor-bundle
php bin/console cache:clear
```

- Client apply PDF bodies must stay under `max_upload_bytes`.
- `add_image` paths must be workspace-relative (no absolute paths, URIs, or `..`). Update any callers that passed absolute paths.

## To 1.0.3

From **1.0.2** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/pdf-editor-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## Table of contents

- [To 1.2.0](#to-120)
- [To 1.1.0](#to-110)
- [To 1.0.5](#to-105)
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
