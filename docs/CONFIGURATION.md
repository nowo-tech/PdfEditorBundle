# Configuration

```yaml
nowo_pdf_editor:
    default_profile: default
    profiles:
        default:
            python_binary: python3
            engine_script: '%kernel.project_dir%/vendor/nowo-tech/pdf-editor-bundle/engine/pdf_editor_engine.py'
            workspace_dir: '%kernel.project_dir%/var/pdf-editor'
            timeout: 60
            idle_timeout: 30
            max_upload_bytes: 26214400
            render_dpi: 144
            engine_mode: client   # client | python
            max_render_pixels: 25000000   # largest page preview (python engine); the dpi is lowered to fit
            engine_memory_limit: 1024     # MiB, RLIMIT_AS of each engine process; 0 = no cap
    web_ui:
        layout_template: null            # null = nowo_ui_kit.layouts.admin
        templates:
            upload: '@NowoPdfEditorBundle/editor/index.html.twig'
            workspace: '@NowoPdfEditorBundle/editor/workspace.html.twig'
    security:
        access_roles: [ROLE_ADMIN]
        access_checker: null             # service id of your PdfEditorAccessCheckerInterface
        allow_ownerless_workspaces: false # true only for stateless callers without user or session (see SECURITY.md)
        allow_unauthenticated: false     # dev/demo only
```

`default_profile` must exist under `profiles` (REQ-CFG-001).

Only the profile named by `default_profile` is used: the engine, the workspace manager and the controller are wired once per application, and there is no route or option that selects another profile. Defining more than one profile is **deprecated since 1.2** (a deprecation is triggered naming the unused profiles; support is removed in 2.0). Keep a single profile and change its values per environment instead (`config/packages/<env>/nowo_pdf_editor.yaml`).

## Table of contents

- [Profiles](#profiles)
- [Security (private access)](#security-private-access)
- [Web UI (layout, look and feel, templates)](#web-ui-layout-look-and-feel-templates)
  - [Form profile (FormKit)](#form-profile-formkit)
- [Translations](#translations)
- [Workspace cleanup (TTL)](#workspace-cleanup-ttl)
- [`engine_mode`](#engine_mode)
- [Workspace ownership](#workspace-ownership)
- [Requirements](#requirements)

## Profiles

| Item | Behaviour |
| --- | --- |
| Canonical keys | `default_profile` + `profiles.<name>` (`python_binary`, `engine_script`, `workspace_dir`, `timeout`, `idle_timeout`, `max_upload_bytes`, `render_dpi`, `engine_mode`, `max_render_pixels`, `engine_memory_limit`) |
| Legacy synonyms | None: the bundle was created with the profiles shape (no `default_config` / `configs` keys) |
| Validation | Container build fails when `default_profile` is not a key of `profiles` |
| Runtime selection | Always `default_profile`: one engine, workspace manager and controller per application; no request, route or option selects another profile |
| Extra profiles | Deprecated since 1.2 (a deprecation names the unused profiles); removed in 2.0 — use per-environment config files instead |

## Security (private access)

The editor is a private UI (REQ-UI-002). Every route checks the access checker; mutations also check CSRF.

| Key | Default | Purpose |
| --- | --- | --- |
| `security.access_roles` | `[ROLE_ADMIN]` | The user must be granted at least one role (`isGranted`). Anonymous users are always denied by the built-in checker. An **empty list is refused** at container build unless `access_checker` is set or `allow_unauthenticated` is true (an empty list never means "everyone"). |
| `security.access_checker` | `null` | Service id of your own `Nowo\PdfEditorBundle\Security\PdfEditorAccessCheckerInterface` (`canUseEditor(?object $user): bool`); the interface and `nowo_pdf_editor.access_checker` are aliased to it. `null` = built-in role checker. |
| `security.allow_unauthenticated` | `false` | **DEV/DEMO only**: no role check, anonymous visitors may use the editor (workspaces are then bound to the session). Refused at container build in the `prod` environment and in non-debug kernels (test environments excepted). |
| `security.allow_ownerless_workspaces` | `false` | Callers with neither a user nor a session (stateless firewall + custom checker) are refused workspaces; `true` lets them in, but such workspaces are reachable by anyone who knows the id. |
| `security.roles` | — | Deprecated since 1.5 (removed in 2.0): mapped onto `access_roles` (when both are set, `access_roles` wins unless it is the default). |

The container also refuses to compile when SecurityBundle is not enabled (the editor binds workspaces to the current
user / session) or when CSRF protection is off (`framework.csrf_protection` / `framework.form.csrf_protection`).

Routes live under `/pdf-editor` (plus the prefix of your routing import). Add the firewall layer in the host:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/pdf-editor, roles: ROLE_ADMIN }   # or ^/admin/pdf-editor with an import prefix
```

## Web UI (layout, look and feel, templates)

The editor pages follow the UiKit page contract (REQ-UI-001 / REQ-UI-003; see [TEMPLATES.md](TEMPLATES.md)): the
upload page is the UiKit form scaffold, the workspace a unique page on the UiKit admin layout. Configure the look and
feel **once, in UiKit**, for every UiKit page of the application:

```yaml
# config/packages/nowo_ui_kit.yaml
nowo_ui_kit:
    css_framework: bootstrap5           # bootstrap5 (bootstrap) | bootstrap4 | tailwind | foundation | custom | none | tabler
    layouts:
        admin: 'layouts/admin.html.twig' # your layout or a one-file bridge rendering the UiKit canonical blocks
    theme:
        tokens: { primary: '#0b6e4f' }   # --nowo-ui-* tokens; class maps under theme.classes
```

| Key | Default | Purpose |
| --- | --- | --- |
| `web_ui.layout_template` | `null` | Per-bundle layout override. `null` = `nowo_ui_kit.layouts.admin`. Set it to your project layout (or a bridge) only when the editor needs a different layout from your other admin pages; the bundle default (UiKit admin layout) is meant for demos / standalone installs. |
| `web_ui.templates.upload` | `@NowoPdfEditorBundle/editor/index.html.twig` | Template rendered for the upload page (a host template that extends `@!NowoPdfEditorBundle/editor/index.html.twig` and redefines one block). |
| `web_ui.templates.workspace` | `@NowoPdfEditorBundle/editor/workspace.html.twig` | Template rendered for the workspace (same idea). |

`css_framework`, `icon_set` and `row_actions_display` are not repeated per bundle: they are inherited from
`nowo_ui_kit` (REQ-UI-003 configuration vocabulary). Examples:

```yaml
nowo_ui_kit:
    css_framework: bootstrap5   # Bootstrap 5 / Tabler hosts: UiKit slot defaults emit Bootstrap classes
    # css_framework: tailwind   # Tailwind hosts
    # css_framework: foundation # Foundation hosts
    # css_framework: custom     # own design system: only nowo-ui-* classes; map them (or the --nowo-ui-* tokens) in your CSS
```

Assets stack with `{{ parent() }}`: the workspace adds `css/pdf-editor.css` and `build/pdf-editor.css` to
`nowo_ui_styles` and the `build/pdf-editor.js` module to `nowo_ui_scripts` (asset package `nowo_pdf_editor`), after the
host / UiKit assets. Content block: `nowo_ui_content`. The bundle has no modals, tables or row actions.

### Form profile (FormKit)

The editor forms (`PdfUploadType` on the upload page, `EditorTextType`) are FormKit Types
(`#[FormKitConfig('pdf_editor')]` + `FormOptionsTrait`, REQ-TWIG-005 rule 11). `nowo-tech/form-kit-bundle` is a
required dependency (register `NowoFormKitBundle`). The bundle prepends the profile
**`nowo_form_kit.profiles.pdf_editor`**:

```yaml
nowo_form_kit:
    profiles:
        pdf_editor:
            alias: pdf_editor
            translation_domain: NowoPdfEditorBundle
            auto_placeholder: false   # the types pass explicit label keys
            auto_help: false
            defaults: { attr: {}, row_attr: {} }   # framework-neutral: the form theme adds the CSS classes
            field_types: {}
```

To change field classes or row attributes (e.g. `field_types.file.attr.class`), define
`nowo_form_kit.profiles.pdf_editor` in your `config/packages/nowo_form_kit.yaml`: when the host defines it, the
bundle does not prepend its own, so copy the keys above and change what you need. The pages set no `form_theme`: the
UiKit form scaffold and your `twig.form_themes` render the fields.

## Translations

UI strings use the `NowoPdfEditorBundle` domain, shipped in `en`, `es`, `it`, `fr`, `pt`, `de` and `nl` (`src/Resources/translations/NowoPdfEditorBundle.<locale>.yaml`, key parity checked by `make validate-translations`). To override them (REQ-I18N-001):

1. Use the same domain, `NowoPdfEditorBundle`.
2. Create `translations/NowoPdfEditorBundle.<locale>.yaml` in the application.
3. Redefine only the keys you need; missing keys fall back to the bundle catalogue (application translations win).

```yaml
# translations/NowoPdfEditorBundle.en.yaml — copy the keys you need from the bundle's NowoPdfEditorBundle.en.yaml
```

## Workspace cleanup (TTL)

Each upload creates a directory under `workspace_dir` (PDF, previews, metadata). Nothing deletes them during requests, so schedule the purge command:

```bash
php bin/console nowo:pdf-editor:purge --older-than=24h   # default 24h; seconds or s/m/h/d/w suffix (90m, 7d, 2w)
php bin/console nowo:pdf-editor:purge --older-than=7d --dry-run   # list only
```

A workspace's age is the newest modification time of the directory or any file in it, so a document still being edited (new apply or preview) is kept. Only directories named like a workspace id (32 hex characters) are considered; symbolic links are removed, never followed. Example cron entry: `0 * * * * php /app/bin/console nowo:pdf-editor:purge --older-than=24h`.

Timeouts apply to every engine process (REQ-RUNTIME-001). Keep PHP `max_execution_time` and the Caddy write timeout **above** `timeout`.

## `engine_mode`

| Value | Behaviour |
|-------|-----------|
| `client` (default) | Editing runs in the browser (embedpdf + pdf-lib). `POST /pdf-editor/{id}/apply` accepts the edited PDF (`Content-Type: application/pdf` or JSON `{"pdf": "<base64>"}`), checks the `%PDF` signature and `max_upload_bytes`, and replaces the workspace PDF. No Python needed; `/inspect` returns `{"engine": "client"}`. |
| `python` | Server-side PyMuPDF engine (`python_binary` + `engine_script`, Python 3.9+ with `pymupdf`). `apply` also accepts JSON `{"ops": [...]}`, executed by the engine under `timeout` / `idle_timeout`; page previews (`/pages/{n}`) are rendered at `render_dpi`. |

## Workspace ownership

Every workspace is bound to the caller that created it: the authenticated user (`getUserIdentifier()`), or — for anonymous access with `allow_unauthenticated: true` — a random token kept in the Symfony session. Only a SHA-256 hash is written to `workspace_dir/<id>/owner.txt`. Any other user or session gets **404** on all `/pdf-editor/{id}/*` routes.

Override `Nowo\PdfEditorBundle\Security\WorkspaceOwnerResolverInterface` (e.g. to share workspaces within a tenant) by aliasing it to your own service.

## Requirements

The `framework` translator / asset packages are prepended only when FrameworkBundle is registered; the
`@NowoPdfEditorBundle` Twig namespace is registered by `TwigPathsPass` when TwigBundle is. `symfony/framework-bundle`,
`symfony/asset`, SecurityBundle, CSRF protection and `nowo-tech/ui-kit-bundle` are runtime requirements (the views use
`asset(..., 'nowo_pdf_editor')` — `/bundles/nowopdfeditor`, or `/bundles/pdfeditor` with the deprecated
`PdfEditorBundle` class — and the UiKit layouts / scaffolds).
