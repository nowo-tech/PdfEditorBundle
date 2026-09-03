# Demos

FrankenPHP demos for **PdfEditorBundle**.

| Demo | Symfony | Path |
|------|---------|------|
| Symfony 8.1 | 8.1.* | [`demo/symfony8`](symfony8/) |

## Quick start

From the **bundle root**:

```bash
make -C demo up-symfony8
```

Or from a demo folder:

```bash
cd demo/symfony8
make up
```

`make up` copies `.env.example` → `.env` if needed, starts Compose, installs Composer deps, and prints:

`Demo started at: http://localhost:<PORT>`

FrankenPHP docs: [../docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md).

## What the demo shows

- **Scenario hub** at `/demo/scenarios` — nine pre-built PDFs (text, forms, watermarks, pages, redaction, notes, images, blank, full playground)
- PDF upload at `/pdf-editor` for your own files
- Visual editor (embedpdf + pdf-lib, `engine_mode: client`)
- Draft-then-save workflow with live annotation editing
- `security.allow_unauthenticated: true` for local use

Regenerate fixture PDFs:

```bash
node demo/scripts/generate-fixtures.mjs
```
