# Feature Specification: PdfEditorBundle baseline

**Feature Branch**: `001-baseline`  
**Status**: Active  

**Package**: `nowo-tech/pdf-editor-bundle`  
**Configuration root**: `nowo_pdf_editor`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Standalone Symfony **PDF editor workspace**: upload a PDF, edit in a CKEditor-like React UI (embedpdf + pdf-lib), save rewritten bytes. Optional legacy Python/PyMuPDF engine. Safe under FrankenPHP worker with kernel not reset between requests.

---

## User Scenarios

### US-01 — Upload and open workspace (P1)

**Given** access is granted, **When** user uploads a PDF at `/pdf-editor`, **Then** `WorkspaceManager` stores it under `workspace_dir/<id>/` and redirects to the workspace UI.

### US-02 — Client-engine draft save (P1)

**Given** `engine_mode: client`, **When** the React editor POSTs `application/pdf` (or base64) to apply, **Then** Symfony replaces `current.pdf` and clears page images + lock files.

### US-03 — Python engine ops (P2)

**Given** `engine_mode: python`, **When** apply sends `{"ops":[...]}`, **Then** `OperationDecoder` + `PythonPdfEngine` rewrite the PDF via Symfony Process with configured timeouts.

### US-04 — Role gate (P1)

**Given** `security.allow_unauthenticated: false`, **When** a request hits editor routes, **Then** `RolePdfEditorAccessChecker` enforces configured roles via `AuthorizationChecker` on every call.

### US-05 — FrankenPHP worker without kernel reboot (P1)

**Given** FrankenPHP worker and `FRANKENPHP_RESET_KERNEL` unset/false, **When** consecutive HTTP requests hit the same worker, **Then** no request-scoped state leaks through bundle services (FR-WORKER-001).

---

## Requirements

### Bundle & config

- **FR-BUNDLE-001**: `PdfEditorBundle` alias `nowo_pdf_editor`.
- **FR-CFG-001**: `Configuration` — `default_profile`, `profiles.*`, security; profile must exist.
- **FR-CFG-002**: `PdfEditorExtension` wires profile, engine, access checker.

### Document & engine

- **FR-DOC-001**: `Workspace` VO + `WorkspaceManager` (create, get, replace, page image path).
- **FR-ENG-001**: `PdfEngineInterface`, `PythonPdfEngine`, `ProcessRunnerInterface`, `SymfonyProcessRunner`, `ProcessOutcome`.
- **FR-ENG-002**: Process `timeout` / `idle_timeout` stop-on-expiry (REQ-RUNTIME-001).
- **FR-OPS-001**: `EditorOperation`, `OperationDecoder`.

### HTTP & forms

- **FR-CTRL-001**: `EditorController` — index, workspace, inspect, pageImage, apply, view, download.
- **FR-FORM-001**: `PdfUploadType`, `EditorTextType`.

### Security & CLI

- **FR-SEC-001**: `PdfEditorAccessCheckerInterface`, `RolePdfEditorAccessChecker`, `AllowAllPdfEditorAccessChecker`.
- **FR-CLI-001**: `nowo:pdf-editor:check-engine`.

### Twig / i18n / frontend

- **FR-TWIG-001**: Editor Twig templates under `Resources/views`.
- **FR-I18N-001**: Translation domains `NowoPdfEditorBundle` (`en`, `es`, `it`, `fr`, `pt`, `de`, `nl`).
- **FR-FE-001**: React assets under `Resources/assets` built to `Resources/public/build`.

### DI

- **FR-DI-001**: `services.yaml`, `routing.yaml`.

### FrankenPHP worker mode

- **FR-WORKER-001**: Safe under FrankenPHP worker with kernel **not** reset between requests (scenario B): every shared service is stateless (`readonly` deps or no properties); user/request never cached; Process and flock handles closed per call; page image locks cleared with PNGs; no `ResetInterface` required. Documented in `docs/FRANKENPHP-WORKER-AUDIT.md`.

---

## Success Criteria

- **SC-001**: Every production PHP/YAML under `src/` (excluding built frontend chunks) mapped in the inventory.
- **SC-002**: Config matches `docs/CONFIGURATION.md`.
- **SC-003**: QA/CI green; PHPStan classic + worker + hardening clean.
- **SC-004**: Worker audit verdict remains 100% compatible under scenario B.

---

## Explicit non-goals

- OCR for scanned pages.
- Built-in workspace TTL purge CLI (host cron / volume policy).
- Demo trees as stable API.

---

## Validation

`make release-check` (CS, Rector, PHPStan, coverage, demo smoke).
