# Configuration

```yaml
nowo_pdf_editor:
    default_profile: default
    profiles:
        default:
            python_binary: python3
            engine_script: '%kernel.project_dir%/vendor/nowo-tech/pdf-editor-bundle/engine/pdf_editor_engine.py'
            workspace_dir: '%kernel.project_dir%/var/pdf-editor'
            timeout: 60
            idle_timeout: 30
            max_upload_bytes: 26214400
            render_dpi: 144
            engine_mode: client   # client | python
    security:
        allow_unauthenticated: false
        roles: [ROLE_ADMIN]
```

`default_profile` must exist under `profiles` (REQ-CFG-001).

Only the profile named by `default_profile` is used: the engine, the workspace manager and the controller are wired once per application, and there is no route or option that selects another profile. Defining more than one profile is **deprecated since 1.2** (a deprecation is triggered naming the unused profiles; support is removed in 2.0). Keep a single profile and change its values per environment instead (`config/packages/<env>/nowo_pdf_editor.yaml`).

## Table of contents

- [Profiles](#profiles)
- [Translations](#translations)
- [Workspace cleanup (TTL)](#workspace-cleanup-ttl)
- [`engine_mode`](#engine_mode)
- [Workspace ownership](#workspace-ownership)
- [Requirements](#requirements)

## Profiles

| Item | Behaviour |
| --- | --- |
| Canonical keys | `default_profile` + `profiles.<name>` (`python_binary`, `engine_script`, `workspace_dir`, `timeout`, `idle_timeout`, `max_upload_bytes`, `render_dpi`, `engine_mode`) |
| Legacy synonyms | None: the bundle was created with the profiles shape (no `default_config` / `configs` keys) |
| Validation | Container build fails when `default_profile` is not a key of `profiles` |
| Runtime selection | Always `default_profile`: one engine, workspace manager and controller per application; no request, route or option selects another profile |
| Extra profiles | Deprecated since 1.2 (a deprecation names the unused profiles); removed in 2.0 — use per-environment config files instead |

## Translations

UI strings use the `NowoPdfEditorBundle` domain, shipped in `en`, `es`, `it`, `fr`, `pt`, `de` and `nl` (`src/Resources/translations/NowoPdfEditorBundle.<locale>.yaml`, key parity checked by `make validate-translations`). To override them (REQ-I18N-001):

1. Use the same domain, `NowoPdfEditorBundle`.
2. Create `translations/NowoPdfEditorBundle.<locale>.yaml` in the application.
3. Redefine only the keys you need; missing keys fall back to the bundle catalogue (application translations win).

```yaml
# translations/NowoPdfEditorBundle.en.yaml — copy the keys you need from the bundle's NowoPdfEditorBundle.en.yaml
```

## Workspace cleanup (TTL)

Each upload creates a directory under `workspace_dir` (PDF, previews, metadata). Nothing deletes them during requests, so schedule the purge command:

```bash
php bin/console nowo:pdf-editor:purge --older-than=24h   # default 24h; seconds or s/m/h/d/w suffix (90m, 7d, 2w)
php bin/console nowo:pdf-editor:purge --older-than=7d --dry-run   # list only
```

A workspace's age is the newest modification time of the directory or any file in it, so a document still being edited (new apply or preview) is kept. Only directories named like a workspace id (32 hex characters) are considered; symbolic links are removed, never followed. Example cron entry: `0 * * * * php /app/bin/console nowo:pdf-editor:purge --older-than=24h`.

Timeouts apply to every engine process (REQ-RUNTIME-001). Keep PHP `max_execution_time` and the Caddy write timeout **above** `timeout`.

## `engine_mode`

| Value | Behaviour |
|-------|-----------|
| `client` (default) | Editing runs in the browser (embedpdf + pdf-lib). `POST /pdf-editor/{id}/apply` accepts the edited PDF (`Content-Type: application/pdf` or JSON `{"pdf": "<base64>"}`), checks the `%PDF` signature and `max_upload_bytes`, and replaces the workspace PDF. No Python needed; `/inspect` returns `{"engine": "client"}`. |
| `python` | Server-side PyMuPDF engine (`python_binary` + `engine_script`, Python 3.9+ with `pymupdf`). `apply` also accepts JSON `{"ops": [...]}`, executed by the engine under `timeout` / `idle_timeout`; page previews (`/pages/{n}`) are rendered at `render_dpi`. |

## Workspace ownership

Every workspace is bound to the caller that created it: the authenticated user (`getUserIdentifier()`), or — for anonymous access with `allow_unauthenticated: true` — a random token kept in the Symfony session. Only a SHA-256 hash is written to `workspace_dir/<id>/owner.txt`. Any other user or session gets **404** on all `/pdf-editor/{id}/*` routes.

Override `Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolverInterface` (e.g. to share workspaces within a tenant) by aliasing it to your own service.

## Requirements

Twig paths and `framework` translator/asset packages are prepended only when TwigBundle / FrameworkBundle are registered. `symfony/framework-bundle` and `symfony/asset` are runtime requirements (the views use `asset(..., 'nowo_pdf_editor')`).
