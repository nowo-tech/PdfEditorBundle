# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0/).

## [Unreleased]

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
