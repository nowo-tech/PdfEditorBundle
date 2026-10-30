# Security Policy

## Table of contents

- [Supported Versions](#supported-versions)
- [Reporting a Vulnerability](#reporting-a-vulnerability)
- [Scope and attack surface](#scope-and-attack-surface)
- [Threat model and mitigations](#threat-model-and-mitigations)
- [Logging](#logging)
- [Dependencies and updates](#dependencies-and-updates)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

We take the security of `PdfEditorBundle` seriously.

Please report vulnerabilities privately by email: **hectorfranco@nowo.tech**.

Do not open public issues for security-sensitive reports.

## Scope and attack surface

This bundle provides:

- HTTP editor UI under `/pdf-editor` (upload, inspect, page ops, apply, view, download)
- Workspace storage under a configured `workspace_dir` (32-hex workspace ids)
- Optional Python/PyMuPDF engine invoked via Symfony `Process` (argv list only)
- Client-side PDF apply path (default `engine_mode: client`) with CSRF-protected POST
- Access checker (`security.access_roles`, default `[ROLE_ADMIN]`; or the host's `security.access_checker` service; `allow_unauthenticated` for demos only)
- Twig pages rendered in the host layout through UiKit (`nowo-tech/ui-kit-bundle`), and the compiled React editor (`src/Resources/public/build`)

## Threat model and mitigations

- **Actors / trust**
  - Treat the editor as a private admin UI.
  - Default `security.allow_unauthenticated: false` and `access_roles: [ROLE_ADMIN]`; anonymous users are always denied by the built-in checker.
  - An empty `access_roles` list is refused at container build (unless a custom `access_checker` is set): it never means "everyone".
  - `allow_unauthenticated: true` (the admin-UI footgun: anyone can upload PDFs and run the engine) is a dev/demo switch, refused at container build in `prod` and in non-debug kernels. Never enable it outside isolated demos.
  - The container refuses to compile without SecurityBundle (token storage / authorization checker) or without CSRF protection, so a misconfigured host cannot silently run the editor unprotected.
  - Add a firewall rule for the editor prefix too (`access_control: - { path: ^/pdf-editor, roles: ROLE_ADMIN }`): the bundle checker is the second layer.
- **HTTP surface**
  - Routes under `/pdf-editor*`; workspace `{id}` constrained to `[a-f0-9]{32}`.
  - CSRF on `POST .../apply` (`X-CSRF-TOKEN` / token id `pdf_editor`).
  - Symfony form CSRF on upload POST (fails closed: compile error when form CSRF is disabled).
- **Workspaces**
  - Random 32-hex ids; reject empty id, `/`, `\`, and `..`.
  - Each workspace is bound to its creator (`owner.txt`: SHA-256 of the user identifier, or of a random token stored in the anonymous session). Every `{id}` route returns **404** for other users/sessions (`WorkspaceOwnerResolverInterface`, overridable).
  - Callers with neither a user nor a session (stateless firewall + a custom `access_checker`) cannot create or open workspaces: they could not be bound to anyone. Opt in with `security.allow_ownerless_workspaces: true` only if you accept that such workspaces are reachable by anyone who knows their 128-bit id.
  - `workspace_dir` defaults to `%kernel.project_dir%/var/pdf-editor` (not the shared system temp dir). The root is created `0700`; the editor refuses a root owned by another account or writable by group / others. Keep it outside the web root and off public file servers.
  - Residual: no per-owner quota (count / bytes). Schedule `nowo:pdf-editor:purge --older-than=24h` (cron or Symfony Scheduler) so abandoned workspaces are removed; rate-limit the upload route in the host if editor users are not trusted.
- **Uploads & client apply**
  - Extension must be `.pdf` **and** the stored file must start with the `%PDF-` signature; create path enforces `max_upload_bytes` (default 25 MiB).
  - `/view` serves the PDF inline with `X-Content-Type-Options: nosniff` and `Content-Security-Policy: sandbox`; `/download` sends `nosniff`.
  - Client apply requires the `%PDF-` signature and `max_upload_bytes`. The body is read through a stream capped at `max_upload_bytes × 4/3` (+64 KiB JSON envelope), so chunked bodies without `Content-Length` are bounded too; a larger declared `Content-Length` is rejected before reading. PHP's `post_max_size` does **not** bound chunked bodies on every SAPI (FrankenPHP / Caddy): also cap the request body at the proxy (`request_body { max_size 40MB }` in Caddy, `client_max_body_size` in nginx). Virus-scan if needed.
  - `/download` is always `Content-Type: application/pdf` (never sniffed) with `nosniff` and `attachment`.
  - Residual: no per-owner workspace quota; abandoned workspaces are removed by `nowo:pdf-editor:purge` (schedule it).
- **Python engine**
  - Argv-only `Process` with `timeout` / `idle_timeout` (defaults 60s / 30s).
  - Each engine process runs with an address-space cap (`RLIMIT_AS`, profile `engine_memory_limit`, default 1024 MiB): decompression or pixmap bombs fail cleanly instead of exhausting the host.
  - Page previews are capped at `max_render_pixels` (default 25 MP): a tiny PDF declaring a huge page is rendered at a lower dpi instead of allocating gigabytes.
  - `/pages/{n}` is 404 in `engine_mode: client` (the browser renders pages) and for pages that do not exist (the page count is cached per PDF revision, so no engine process is spawned for them; failed renders leave no lock files).
  - `apply` accepts at most 500 operations; the engine refuses blank pages larger than 14400 pt and growing a document above 2000 pages (larger uploads stay editable but cannot grow).
  - Engine errors reach exception messages as one line with absolute paths replaced by `[path]` (no tracebacks).
  - Residual: no global limit on concurrent engine processes — size PHP workers accordingly.
  - Never pass unsanitized shell strings.
- **XSS / output**
  - Twig escapes config JSON for HTML attributes; prefer `textContent` in legacy JS.
  - Pages carry no inline `on*=` handlers, no `javascript:` URLs and no CDN assets (enforced by `tests/Integration/PageContractTest.php`).
- **Secrets**
  - No bundle feature requires hardcoded secrets; keep `.env` untracked.

## Logging

The bundle logs through the injected `logger` service (REQ-OBS-001):

| Event | Level | Context |
| --- | --- | --- |
| Workspace created from an upload | `info` | `bundle`, `workspace` (random id), `bytes` |
| Workspaces purged (`nowo:pdf-editor:purge`, TTL) | `info` | `bundle`, `count`, `max_age_seconds`, `dry_run` |
| Python engine timed out | `warning` | `bundle`, `command` (`inspect` / `render` / `apply` / `version`), `timeout` |
| Python engine exited with an error | `error` | `bundle`, `command`, `exit_code` |

Never logged: PDF contents or text, original file names, workspace paths, owner identifiers, CSRF tokens or engine stderr.

## Dependencies and updates

- Run `composer audit` regularly (also in CI when published).
- Keep Symfony, PyMuPDF/Python tooling, and frontend (Vite/TS) dependencies updated.
- Re-audit after enabling Python mode or changing `python_binary` / `engine_script`.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current and linked from the README where applicable. |
| **`.gitignore` and `.env`** | `.env` and local env files are ignored; no committed secrets. |
| **No secrets in repo** | No API keys, passwords, or tokens in tracked files. |
| **Recipe / Flex** | Default recipe keeps `allow_unauthenticated: false` and `ROLE_ADMIN`. |
| **Input / output** | Upload extension/size limits; CSRF on apply; Twig/JS escaping preserved. |
| **Dependencies** | `composer audit` run; issues triaged. |
| **Logging** | Logs do not print secrets, tokens, or session identifiers unnecessarily. |
| **Process / engine** | Argv-only Process; timeouts set; Python mode residuals documented. |
| **Permissions / exposure** | Editor firewall + access checker; `workspace_dir` not web-exposed. |
| **Limits / DoS** | `max_upload_bytes` on create; host body-size limits for client apply. |
| **REQ-SEC-004 (AI audit)** | Appendix BT, **2026-10-30**, on 1.5.0 as merged (`7b315cb`): **Pass (conditional) / Medium** — M1 (unbounded render memory / page numbers), L1 (chunked apply bodies), L2 (lock-file litter, no quota), L3 (owner-less workspaces), L4 (shared `/tmp` workspace root), I1 / I4 / I5. Fixed before the 1.5.0 tag (see CHANGELOG `### Security`); remaining residuals: no per-owner quota, no global engine concurrency limit, purge must be scheduled. Expected grade after re-verification: Pass (good) / Low. See monorepo `BUNDLES_SECURITY_ANALYSIS.md`. |

Record confirmation in the release PR or tag notes.
