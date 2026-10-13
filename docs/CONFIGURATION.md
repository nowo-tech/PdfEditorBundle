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
