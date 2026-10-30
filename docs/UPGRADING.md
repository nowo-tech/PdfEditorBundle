# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.5.0](#to-150)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
- [To 1.3.1](#to-131)
- [To 1.3.0](#to-130)
- [To 1.2.2](#to-122)
- [To 1.2.1](#to-121)
- [To 1.2.0](#to-120)
- [To 1.1.0](#to-110)
- [To 1.0.5](#to-105)
- [To 1.0.4](#to-104)
- [To 1.0.3](#to-103)
- [From 1.0.1 to 1.0.2](#from-101-to-102)
  - [Notes](#notes)
- [From 1.0.0 to 1.0.1](#from-100-to-101)
- [From nothing → 1.0.0](#from-nothing--100)

## Unreleased

## To 1.5.0

From **1.4.1**:

```bash
composer update nowo-tech/pdf-editor-bundle
```

From **1.4.1**. Requires Symfony **7.4 / 8.0 / 8.1** (6.4 and 7.0–7.3 are no longer supported: the pages now use
`nowo-tech/ui-kit-bundle` 2.x, installed as a dependency).

```bash
composer update nowo-tech/pdf-editor-bundle --with-dependencies
```

1. **Bundle class** (deprecated alias kept until 2.0). In `config/bundles.php` register UiKit and the new class:

   ```php
   Nowo\UiKitBundle\NowoUiKitBundle::class => ['all' => true],
   Nowo\PdfEditorBundle\NowoPdfEditorBundle::class => ['all' => true],   // was Nowo\PdfEditorBundle\PdfEditorBundle
   ```

   and change the routing import to `resource: '@NowoPdfEditorBundle/Resources/config/routing.yaml'` (route names and
   paths are unchanged). Then run `php bin/console assets:install`: public assets move from `/bundles/pdfeditor/` to
   `/bundles/nowopdfeditor/` (the `nowo_pdf_editor` asset package follows automatically). Keeping the old class works
   (deprecation notice; assets stay under `/bundles/pdfeditor/`), but you still have to register `NowoUiKitBundle`.
2. **Security keys** (REQ-UI-002): rename `nowo_pdf_editor.security.roles` to `security.access_roles` (the old key is
   deprecated and mapped). New `security.access_checker` (service id of your `PdfEditorAccessCheckerInterface`). An
   empty `access_roles` list without a custom checker now fails the container build instead of locking everybody out.
   The container also fails to build without SecurityBundle or with CSRF protection disabled.
3. **Security bounds** (REQ-SEC-004 audit BT): `workspace_dir` now defaults to `%kernel.project_dir%/var/pdf-editor`
   (set it explicitly to keep the old `/tmp/nowo-pdf-editor`; the root must belong to the PHP user and must not be
   group / world writable). `/pdf-editor/{id}/pages/{n}` is 404 in `engine_mode: client`. Callers without user and
   session get no workspace unless `security.allow_ownerless_workspaces: true`. New profile keys
   `max_render_pixels` / `engine_memory_limit` (defaults 25 MP / 1024 MiB). Install `pymupdf>=1.26.7`.
4. **Template contract** (REQ-UI-003 / REQ-TWIG-002):
   - Twig namespace: `@NowoPdfEditorBundle/...` (application overrides under `templates/bundles/NowoPdfEditorBundle/`).
     `@PdfEditor/...` still resolves to the bundle views until 2.0, but the bundle renders `@NowoPdfEditorBundle/...`,
     so files under `templates/bundles/PdfEditorBundle/` **no longer override the editor pages**: move them to
     `templates/bundles/NowoPdfEditorBundle/` (and replace `{% extends '@NowoPdfEditorBundle/…' %}` inside them by
     `@!NowoPdfEditorBundle/…`).
   - The pages render inside the UiKit admin layout (`nowo_ui_kit.layouts.admin`, or the new
     `nowo_pdf_editor.web_ui.layout_template`) instead of the standalone `editor/layout.html.twig` HTML document. Set
     `nowo_ui_kit.layouts.admin` to your layout (or a bridge) once. Overrides of `editor/layout.html.twig` made only to
     add host chrome can be deleted.
   - `editor/index.html.twig` (upload) is now the UiKit form scaffold with a `page` view model
     (`PdfEditorPages::upload()`); its 1.x block `nowo_pdf_editor_body` is gone — use `nowo_ui_form_aside` /
     `nowo_ui_form_body`, or the UiKit theme, instead of a full fork.
   - `editor/workspace.html.twig` is a unique page: it keeps the `nowo_pdf_editor_body` and `nowo_pdf_editor_scripts`
     blocks (now inside `nowo_ui_content` / `nowo_ui_scripts`) and accepts a `nowo_pdf_editor_config` hash merged into
     the editor configuration; the editor config now always contains the complete `i18n` map and `layout.mode: embed`.
   - `editor/layout.html.twig` and `editor/_toolbar.html.twig` are deprecated (removed in 2.0). The layout is a shim
     on the UiKit layout that maps `nowo_pdf_editor_title` / `nowo_pdf_editor_body` / `nowo_pdf_editor_scripts` onto
     the canonical blocks.
   - CSS: the editor's private classes are renamed from `nowo-ui-btn*`, `nowo-ui-input`, `nowo-ui-label`,
     `nowo-ui-muted`, `nowo-ui-subtitle`, `nowo-ui-toast` to `nowo-pdf-editor-btn*`, `nowo-pdf-editor-input`, … so they
     no longer restyle UiKit's `nowo-ui-*` classes on the host page. Update host CSS that targeted the old names inside
     the editor. `.nowo-ui-body`, `.nowo-ui-card` and `.nowo-ui-title` are no longer styled by `css/pdf-editor.css`.

## To 1.4.1

From **1.4.0**:

```bash
composer update nowo-tech/pdf-editor-bundle
```

## To 1.4.0

From **1.3.1**:

```bash
composer update nowo-tech/pdf-editor-bundle
```

## To 1.3.1

From **1.3.0**:

```bash
composer update nowo-tech/pdf-editor-bundle
```

## To 1.3.0

From **1.2.2**:

```bash
composer update nowo-tech/pdf-editor-bundle
```

- Run `composer update nowo-tech/pdf-editor-bundle -W`: `twig/extra-bundle` and `twig/string-extra` are now required. Register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php` if Flex did not.
- Routes keep their names and paths; keep importing `@PdfEditorBundle/Resources/config/routing.yaml`.
- If you referenced the root `assets/` or `editor/` directories of the package, use `src/Resources/assets` / `src/Resources/views` (the root copies were stale and are removed).

## To 1.2.2

From **1.2.1**:

```bash
composer update nowo-tech/pdf-editor-bundle
```

## To 1.2.1

From **1.2.0** — upload form renders enctype once

```bash
composer update nowo-tech/pdf-editor-bundle
```

- No breaking changes. No application upgrade steps.

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
