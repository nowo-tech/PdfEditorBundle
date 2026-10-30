# Feature Specification: PdfEditorBundle baseline

**Feature Branch**: `001-baseline`  
**Status**: Active  

**Package**: `nowo-tech/pdf-editor-bundle`  
**Configuration root**: `nowo_pdf_editor`  
**Code inventory**: [`code-inventory.md`](code-inventory.md)

---

## Summary

Standalone Symfony **PDF editor workspace**: upload a PDF, edit in a CKEditor-like React UI (embedpdf + pdf-lib), save rewritten bytes. Optional Python/PyMuPDF engine (`engine_mode: python`). Safe under FrankenPHP worker with kernel not reset between requests.

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

- **FR-BUNDLE-001**: `NowoPdfEditorBundle` (extension alias `nowo_pdf_editor`; registers `TwigPathsPass`, `SecurityBundlePass`, `CsrfProtectionPass`); `PdfEditorBundle` is its deprecated 1.x alias (removed in 2.0).
- **FR-CFG-001**: `Configuration` — `default_profile`, `profiles.*`, `web_ui` (`layout_template`, `templates.*`), `security` (`access_roles`, `access_checker`, `allow_unauthenticated`; deprecated `roles`); profile must exist; empty `access_roles` without a checker is refused.
- **FR-CFG-002**: `PdfEditorExtension` wires profile, engine, access checker.

### Document & engine

- **FR-DOC-001**: `Workspace` VO + `WorkspaceManager` (create, get, replace, page image path).
- **FR-ENG-001**: `PdfEngineInterface`, `PythonPdfEngine`, `ProcessRunnerInterface`, `SymfonyProcessRunner`, `ProcessOutcome`.
- **FR-ENG-002**: Process `timeout` / `idle_timeout` stop-on-expiry (REQ-RUNTIME-001); engine address-space cap (`engine_memory_limit`), preview pixel cap (`max_render_pixels`), at most 500 operations per apply and 2000 pages per document (REQ-SEC-004).
- **FR-OPS-001**: `EditorOperation`, `OperationDecoder`.

### HTTP & forms

- **FR-CTRL-001**: `EditorController` — index, workspace, inspect, pageImage, apply, view, download.
- **FR-FORM-001**: `PdfUploadType`, `EditorTextType`.

### Security & CLI

- **FR-SEC-001**: `PdfEditorAccessCheckerInterface`, `RolePdfEditorAccessChecker`, `AllowAllPdfEditorAccessChecker`.
- **FR-SEC-002**: `WorkspaceOwnerResolverInterface` / `WorkspaceOwnerResolver` bind each workspace to its owner (user identifier when authenticated, a random token kept in the session for anonymous callers) so one user cannot open another's workspace.
- **FR-CLI-001**: `nowo:pdf-editor:check-engine`.
- **FR-CLI-002**: `nowo:pdf-editor:purge` deletes workspaces (uploaded PDF, previews, metadata) not touched for a given age; hosts schedule it (cron, Symfony Scheduler).

### Twig / i18n / frontend

- **FR-TWIG-001**: Editor Twig templates under `Resources/views` (`@NowoPdfEditorBundle`): upload page on the UiKit form scaffold, workspace unique page on the UiKit admin layout (`docs/TEMPLATES.md`); `TwigPathsPass` registers application overrides first.
- **FR-UI-001**: `View/PdfEditorPages` builds the UiKit view models (plain data) of the editor pages (REQ-UI-003).
- **FR-SEC-003**: `SecurityBundlePass` / `CsrfProtectionPass` fail the container build without SecurityBundle or CSRF protection.
- **FR-I18N-001**: Translation domains `NowoPdfEditorBundle` (`en`, `es`, `it`, `fr`, `pt`, `de`, `nl`).
- **FR-FE-001**: React editor (`main.tsx`, `pdf-editor-app.tsx`, `components/*`, `styles/editor.css`, `types.ts`, `op-labels.ts`) under `Resources/assets/src`.
- **FR-FE-002**: Client engine (`engines/client-engine.ts`, default `engine_mode: client`) rewrites the PDF in the browser with pdf-lib; `engines/vensas/*` hold the content-stream / XObject helpers adapted from vensas/pdf-editor (MIT).
- **FR-FE-003**: embedpdf bridges (`embedpdf-bridge.ts`, `selection-bridge.ts`, `object-selection-bridge.ts`, `redaction-bridge.ts`) connect the viewer plugins to the editor state.
- **FR-FE-004**: `Resources/public/css/pdf-editor.css` (editor surface styles loaded by `editor/workspace.html.twig`) and the legacy `Resources/public/js/pdf-editor.js` shim ship as static assets.
- **FR-BUILD-001**: `Resources/public/build/**` (`pdf-editor.js`, `pdf-editor.css`, `pdf-editor.wasm`, `chunks/*`) is the committed Vite build output of `Resources/assets/src/main.tsx` (`vite.config.ts`), never edited by hand.

### DI

- **FR-DI-001**: `services.yaml`, `routing.yaml`.

### FrankenPHP worker mode

- **FR-WORKER-001**: Safe under FrankenPHP worker with kernel **not** reset between requests (scenario B): every shared service is stateless (`readonly` deps or no properties); user/request never cached; Process and flock handles closed per call; page image locks cleared with PNGs; no `ResetInterface` required. Documented in `docs/FRANKENPHP-WORKER-AUDIT.md`.

---

## Success Criteria

- **SC-001**: **74/74** tracked files under `src/` mapped in [`code-inventory.md`](code-inventory.md) (build outputs as FR-BUILD-001 rows).
- **SC-002**: Config matches `docs/CONFIGURATION.md`.
- **SC-003**: QA/CI green; PHPStan classic + worker + hardening clean.
- **SC-004**: Worker audit verdict remains 100% compatible under scenario B.

---

## Explicit non-goals

- OCR for scanned pages.
- Scheduling the workspace purge: `nowo:pdf-editor:purge` exists, the host decides when to run it (cron, Symfony Scheduler).
- Demo trees as stable API.

---

## Validation

`make release-check` (CS, Rector, PHPStan, coverage, demo smoke).
