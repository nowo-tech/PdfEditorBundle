# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0/).

## [Unreleased]

### Security

- Workspaces are bound to the user (or anonymous session) that created them; every `/pdf-editor/{id}` route now returns **404** to anyone else instead of serving, editing or downloading the PDF. New `WorkspaceOwnerResolverInterface` (default `WorkspaceOwnerResolver`) can be overridden.
- Uploads must start with the `%PDF-` signature after being stored; files that only carry a `.pdf` extension are rejected and removed.
- `/view` sends `X-Content-Type-Options: nosniff` and `Content-Security-Policy: sandbox`; `/download` sends `nosniff`.

### Fixed

- Unknown workspace ids now produce a 404 instead of a 500.
- English catalogue completed (86 missing keys — the English UI showed raw `pdf_editor.*` ids); de/fr/it/nl/pt gained the 33 keys only Spanish had. A parity test keeps all locales in sync with English.
- `prepend()` only configures Twig / FrameworkBundle when those extensions are registered.

### Changed

- `symfony/framework-bundle` (was require-dev) and `symfony/asset` are now runtime requirements.

### Documentation

- `docs/CONFIGURATION.md` documents `engine_mode`, workspace ownership and runtime requirements.

## [1.0.5] - 2026-10-09

### Dependencies

- Bundle lockfile: Symfony 7.4.20, `twig/twig` 3.30.0; dev `phpstan/phpstan` 2.3.1, `phpstan/phpstan-symfony` 2.1.0, `nowo-tech/phpstan-frankenphp` 1.2.3, `rector/rector` 2.7.0, `friendsofphp/php-cs-fixer` 3.95.27 (style pass on `EditorController`).
- Demo (`demo/symfony8`): Symfony 8.1.8, `twig/twig` 3.30.0; regenerated `config/reference.php`.

## [1.0.4] - 2026-09-28

### Security

- Client apply PDF bodies respect `max_upload_bytes`.
- `add_image` paths must be workspace-relative (no absolute paths, URIs, or `..`); PHP binds them under the workspace directory and the Python engine refuses paths that escape the PDF parent.

## [1.0.3] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.0.5]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.0.5
[1.0.4]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.0.4
[1.0.3]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.0.3

## [1.0.2] - 2026-09-24

### Added

- **FrankenPHP worker / `reset_kernel: false`:** full compatibility audit confirming 100% safety under a long-lived kernel (scenario B). Docs: [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).
- Baseline specs (`specs/001-baseline/spec.md`, `code-inventory.md`) including **FR-WORKER-001**.
- PHPStan includes `ruleset-hardening.neon` (in addition to classic + worker).

### Fixed

- Page image lock files (`page-*.png.lock`) are removed together with stale PNGs after apply, so long-lived workers do not accumulate empty locks on disk.
- PHPStan level 8 generics on form types (`PdfUploadType`, `EditorTextType`).
- Demo `FixturesLocator.php` duplicated class body that broke Symfony DI / PHPUnit parse.
- Restored empty demo `messages.en.yaml` / `messages.es.yaml` so i18n tests and the FrankenPHP landing page render correctly.
- Enabled Symfony session in the demo (required for locale switch under FrankenPHP worker).
- Removed duplicate `pdf_editor.pages.current` keys in `fr` / `nl` / `pt` translation catalogues.
- Demo `release-verify` now fails if `make up` fails (`set -e`).
- Restored empty React sources (`PdfEditorApp.tsx`, `object-selection-bridge.ts`) so `pnpm run build` works again.
- Synced demo `pnpm-lock.yaml` with Vite 8 / `vite-plugin-symfony` 8.2.

### Changed

- `AllowAllPdfEditorAccessChecker` is `final readonly` (already stateless).
- Demo / README / UPGRADING document `FRANKENPHP_RESET_KERNEL` default `false`.

### Notes

- **No required host changes.** Keep `engine_mode: client` (default) or tune Process timeouts when using `python`.

## [1.0.1] - 2026-09-10

### Fixed

- Restored duplicated PHP sources and raised coverage above 99%.
- Coverage / onboarding and Security / Contributing doc polish.

### Changed

- Composer lockfile refresh.

## [1.0.0] - 2026-08-24

### Added

- First isolated PDF editor bundle with CKEditor-like UI.
- Default free client engine (embedpdf + pdf-lib); optional Python 3 + PyMuPDF.
- Text, AcroForm, watermarks, page ops, annotations.

[1.0.2]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.0.2
[1.0.1]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.0.0
