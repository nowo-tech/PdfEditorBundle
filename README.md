# PDF Editor Bundle

[![CI](https://github.com/nowo-tech/PdfEditorBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/PdfEditorBundle/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%2B%20%7C%207%20%7C%208-000000?logo=symfony)](https://symfony.com)

> ⭐ **Found this useful?** Give it a star on [GitHub](https://github.com/nowo-tech/PdfEditorBundle).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

**FrankenPHP worker mode:** Supported — the Python engine is executed with Symfony Process wall-clock and idle timeouts; demos ship FrankenPHP with `FRANKENPHP_MODE` (see [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)).

## Table of contents

- [What is this?](#what-is-this)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Documentation](#documentation)
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
- Named profiles (`default_profile` + `profiles`) — REQ-CFG-001
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
- [Architecture decisions (ADR)](docs/adr/README.md)
- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)
- [Security](docs/SECURITY.md)
- [Testing](docs/TESTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Contributing](docs/CONTRIBUTING.md)

## License

MIT. The PHP bundle is MIT. The required PyMuPDF engine is a separate free Python dependency installed on the host (see `engine/requirements.txt`).
