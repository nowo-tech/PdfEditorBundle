# Engram

Short facts for AI assistants and maintainers.

- Package: `nowo-tech/pdf-editor-bundle`
- Bundle class: `Nowo\PdfEditorBundle\PdfEditorBundle`
- Config alias: `nowo_pdf_editor`
- Main services: `EditorController`, `WorkspaceManager`, `PdfEngineInterface` / `PythonPdfEngine`, `PdfEditorAccessCheckerInterface`
- Profiles: `default_profile` + `profiles.*` (engine_mode, python_binary, timeouts, workspace_dir, security)
- Engines: default `client` (browser pdf-lib / embedpdf); optional `python` (PyMuPDF via Symfony Process)
- Routes: `/pdf-editor` (upload, workspace, inspect, page image, apply, view, download)
- FrankenPHP: **worker + `reset_kernel: false` supported** — all shared services are stateless; see [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md)
- Frontend: React editor under `src/Resources/assets` → `make assets`
- Specs: `specs/001-baseline/spec.md` + `code-inventory.md`
