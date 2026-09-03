# Security Policy

## Table of contents

- [Supported Versions](#supported-versions)
- [Reporting a Vulnerability](#reporting-a-vulnerability)
- [Scope and attack surface](#scope-and-attack-surface)
- [Threat model and mitigations](#threat-model-and-mitigations)
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
- Access checker (`ROLE_ADMIN` by default; `allow_unauthenticated` for demos only)

## Threat model and mitigations

- **Actors / trust**
  - Treat the editor as a private admin UI.
  - Default `security.allow_unauthenticated: false` and `roles: [ROLE_ADMIN]`.
  - Never enable unauthenticated access outside isolated demos.
- **HTTP surface**
  - Routes under `/pdf-editor*`; workspace `{id}` constrained to `[a-f0-9]{32}`.
  - CSRF on `POST .../apply` (`X-CSRF-TOKEN` / token id `pdf_editor`).
  - Symfony form CSRF on upload POST.
- **Workspaces**
  - Random 32-hex ids; reject empty id, `/`, `\`, and `..`.
  - Residual shared-admin IDOR if a principal learns another workspace id.
  - Keep `workspace_dir` outside the web root and off public file servers.
- **Uploads & client apply**
  - Extension must be `.pdf`; create path enforces `max_upload_bytes` (default 25 MiB).
  - Client apply checks `%PDF` magic; residual: apply body size is not capped by the bundle — set host PHP/`client_max_body_size` limits and virus-scan if needed.
- **Python engine**
  - Argv-only `Process` with `timeout` / `idle_timeout` (defaults 60s / 30s).
  - Never pass unsanitized shell strings.
  - Residual: when `engine_mode: python`, `add_image.path` can read arbitrary readable files — restrict to workspace-local paths or keep client mode.
- **XSS / output**
  - Twig escapes config JSON for HTML attributes; prefer `textContent` in legacy JS.
- **Secrets**
  - No bundle feature requires hardcoded secrets; keep `.env` untracked.

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
| **REQ-SEC-004 (AI audit)** | Pass (conditional) — Medium residual (python `add_image` path, client-apply size, workspace IDOR); see monorepo `BUNDLES_SECURITY_ANALYSIS.md` (audit **2026-09-04**). |

Record confirmation in the release PR or tag notes.
