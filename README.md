# PDF Editor Bundle

[![CI](https://github.com/nowo-tech/PdfEditorBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/PdfEditorBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/pdf-editor-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/pdf-editor-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/pdf-editor-bundle.svg)](https://packagist.org/packages/nowo-tech/pdf-editor-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/pdf-editor-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/PdfEditorBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** Give it a star on [GitHub](https://github.com/nowo-tech/PdfEditorBundle).

Standalone Symfony **PDF editor**: upload a PDF, edit it in a React editor (embedpdf + pdf-lib) and save the rewritten file — text, form fields, pages, notes and watermarks.

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

This includes **`reset_kernel: false`** (long-lived kernel).

**FrankenPHP worker mode:** Supported at **100%** under scenario B (kernel not rebooted). All shared services are stateless; Process timeouts stop the Python engine on expiry. Demo defaults: `FRANKENPHP_MODE=worker`, `FRANKENPHP_RESET_KERNEL` unset (`false`). See [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md) and the [worker audit](docs/FRANKENPHP-WORKER-AUDIT.md).

## Table of contents

- [What is this?](#what-is-this)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Documentation](#documentation)
- [Tests and coverage](#tests-and-coverage)
- [License](#license)

## What is this?

**PdfEditorBundle** is a **standalone** Symfony editor for PDF files, with a document UI comparable to a rich-text editor (toolbar, page canvas, properties panel).

It is **not** an extension of PdfSignableBundle. Signing workflows stay in that package. This bundle owns content editing.

Users can:

- edit existing text (replace / insert / delete)
- create and maintain AcroForm fields
- detect and remove watermarks, or stamp a new one
- rotate, delete, insert and reorder pages
- add notes
- download the rewritten PDF

PHP orchestrates HTTP, security and the UI. The browser runs a **React editor** (`embedpdf` + `pdf-lib`, with XObject helpers inspired by [vensas/pdf-editor](https://github.com/vensas/pdf-editor)). On **Save to PDF**, the client exports the document and Symfony stores the rewritten bytes.

A legacy **Python 3 + PyMuPDF** engine remains available when `engine_mode: python` is set in the profile.

A PDF is not a word-processor file: scanned pages without text need OCR (out of v1). Flattened artwork cannot always be un-drawn. The editor documents those limits instead of pretending otherwise.

## Features

- Isolated admin UI (`/pdf-editor`) following REQ-UI-001 / REQ-UI-002
- Draft-then-save: the React client queues edits, exports via embedpdf/pdf-lib, then Symfony persists the PDF
- Default `engine_mode: client` (no Python required); optional `python` profile for PyMuPDF
- Editor profile (`default_profile` + `profiles`) — REQ-CFG-001; only the default profile is used (extra profiles are deprecated)
- Workspace TTL: `nowo:pdf-editor:purge --older-than=24h` deletes abandoned workspaces
- Process timeouts and stop-on-expiry — REQ-RUNTIME-001
- Twig forms only (`form_start` / `form_row` / `form_end`)
- Translations: `en`, `es`, `it`, `fr`, `pt`, `de`, `nl`

## Requirements

- PHP >= 8.2, < 8.6
- Symfony 6.4 / 7 / 8
- Node >= 20 + pnpm (to build the React editor assets)
- Python 3.9+ and `pymupdf` — **only when** `engine_mode: python`

## Installation

```bash
composer require nowo-tech/pdf-editor-bundle
cd vendor/nowo-tech/pdf-editor-bundle && pnpm install && pnpm run build
php bin/console assets:install public
# Optional legacy Python engine:
# python3 -m pip install -r vendor/nowo-tech/pdf-editor-bundle/engine/requirements.txt
# php bin/console nowo:pdf-editor:check-engine
```

See [docs/INSTALLATION.md](docs/INSTALLATION.md).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Architecture decisions (ADR)](docs/adr/README.md)
- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)
- [FrankenPHP worker audit (`reset_kernel: false`)](docs/FRANKENPHP-WORKER-AUDIT.md)
- [GitHub CI](docs/GITHUB_CI.md)
- [PSR evaluation (REQ-CS-007)](docs/PSR.md)
- [Testing](docs/TESTING.md)
- [Baseline spec](specs/001-baseline/spec.md)

## Tests and coverage

- Tests: PHPUnit (`Unit`, `Integration` suites); see [docs/TESTING.md](docs/TESTING.md).
- PHP: 100% line coverage of `src/` (`composer coverage-check` / `composer test-coverage-100` fails below 100%).
- TS/JS: N/A — no unit tests yet; the React editor is type-checked (`make test-ts`) and built in CI.
- Python: 98.46% lines of `engine/pdf_editor_engine.py` (`make test-python`, pytest-cov, fails below 90%).

```bash
make test
make test-coverage
make release-check
```

## License

MIT. The PHP bundle is MIT. The required PyMuPDF engine is a separate free Python dependency installed on the host (see `engine/requirements.txt`).
