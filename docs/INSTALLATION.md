# Installation

## Requirements

- PHP >= 8.2, < 8.6
- Symfony 7.4 / 8.0 / 8.1+ and nowo-tech/ui-kit-bundle 2.17.3+ (a dependency; register `NowoUiKitBundle` too)
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
    // ...
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Nowo\UiKitBundle\NowoUiKitBundle::class => ['all' => true],
    Nowo\PdfEditorBundle\NowoPdfEditorBundle::class => ['all' => true],
];
```

```yaml
# config/routes/nowo_pdf_editor.yaml
nowo_pdf_editor:
    resource: '@NowoPdfEditorBundle/Resources/config/routing.yaml'
    # prefix: /admin   # optional: the editor lives under <prefix>/pdf-editor
```

Protect the editor in your firewall too (REQ-UI-002; the bundle checks `security.access_roles` as a second layer):

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/pdf-editor, roles: ROLE_ADMIN }
```

Point the editor pages at your layout once, through UiKit (the pages are a UiKit form scaffold and a unique page on
the UiKit admin layout — see [TEMPLATES.md](TEMPLATES.md)):

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    layouts:
        admin: 'layouts/admin.html.twig'   # your layout or a one-file bridge rendering the UiKit canonical blocks
```

Publish the public assets (`/bundles/nowopdfeditor/` and `/bundles/nowouikit/`):

```bash
php bin/console assets:install
```

Upgrading from 1.4 or earlier (`PdfEditorBundle` class, `@PdfEditor` templates, `security.roles`): see
[UPGRADING.md](UPGRADING.md).

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
