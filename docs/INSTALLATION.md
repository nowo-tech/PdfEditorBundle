# Installation

## Requirements

- PHP >= 8.2, < 8.6
- Symfony 6.4+ / 7 / 8
- Composer 2
- Python 3.9+
- PyMuPDF: `python3 -m pip install pymupdf` (free, required)

## Composer

```bash
composer require nowo-tech/pdf-editor-bundle
```

Flex registers the bundle, `config/packages/nowo_pdf_editor.yaml` and routes.

Without Flex:

```php
// config/bundles.php
return [
    Nowo\PdfEditorBundle\PdfEditorBundle::class => ['all' => true],
];
```

```yaml
# config/routes/nowo_pdf_editor.yaml
nowo_pdf_editor:
    resource: '@PdfEditorBundle/Resources/config/routing.yaml'
```

Install the Python engine:

```bash
python3 -m pip install -r vendor/nowo-tech/pdf-editor-bundle/engine/requirements.txt
php bin/console nowo:pdf-editor:check-engine
```

Point `engine_script` at the script inside the Composer package or copy `engine/pdf_editor_engine.py` into the project.

Schedule the workspace cleanup (uploads are otherwise kept forever in `workspace_dir`):

```bash
php bin/console nowo:pdf-editor:purge --older-than=24h
```

See [CONFIGURATION.md](CONFIGURATION.md).
