# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0/).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.4.1] - 2026-10-28](#141---2026-10-28)
- [[1.4.0] - 2026-10-28](#140---2026-10-28)
- [[1.3.1] - 2026-10-24](#131---2026-10-24)
- [[1.3.0] - 2026-10-21](#130---2026-10-21)
- [[1.2.2] - 2026-10-20](#122---2026-10-20)
- [[1.2.1] - 2026-10-13](#121---2026-10-13)
- [[1.2.0] - 2026-10-13](#120---2026-10-13)
- [[1.1.0] - 2026-10-13](#110---2026-10-13)
- [[1.0.5] - 2026-10-09](#105---2026-10-09)
- [[1.0.4] - 2026-09-28](#104---2026-09-28)
- [[1.0.3] - 2026-09-27](#103---2026-09-27)
- [[1.0.2] - 2026-09-24](#102---2026-09-24)
- [[1.0.1] - 2026-09-10](#101---2026-09-10)
- [[1.0.0] - 2026-08-24](#100---2026-08-24)

## [Unreleased]

### Security

REQ-SEC-004 audit (Appendix BT, 2026-10-30) of the merged 1.5.0 code:

- **M1 — bounded page rendering.** Engine processes run with an address-space cap (`RLIMIT_AS`, new profile key
  `engine_memory_limit`, default 1024 MiB); page previews are capped at `max_render_pixels` (default 25 MP, the dpi is
  lowered); `/pdf-editor/{id}/pages/{page}` is 404 in `engine_mode: client` and for pages that do not exist (page
  count cached per PDF revision, checked before spawning Python; page numbers limited to 5 digits); `apply` accepts at
  most 500 operations; the engine refuses blank pages above 14400 pt and documents above 2000 pages.
- **L1** — the apply body is read through a stream capped at the `max_upload_bytes` allowance, so chunked bodies
  without `Content-Length` are bounded too.
- **L2** — failed page renders no longer leave `page-N.png.lock` files behind.
- **L3** — callers with neither a user nor a session can no longer create or open workspaces (fail closed); opt in with
  `security.allow_ownerless_workspaces: true`.
- **L4** — `workspace_dir` defaults to `%kernel.project_dir%/var/pdf-editor` (was the shared system temp dir); the root
  is created `0700` and refused when another account owns it or it is group / world writable.
- **I1** — client apply requires the full `%PDF-` signature; `/download` is always `Content-Type: application/pdf`.
- **I4** — `pymupdf>=1.26.7` (engine requirements and Docker images).
- **I5** — engine errors reach exception messages as one line without absolute paths or tracebacks.

### Fixed

- **Routes stay opt-in on Symfony 7.3+.** The editor controller is no longer autoconfigured (and drops `#[AsController]`; it keeps the `controller.service_arguments` tag explicitly), so the host's default `routing.controllers` import no longer loads the `nowo_pdf_editor_*` routes without importing `@NowoPdfEditorBundle/Resources/config/routing.yaml` (bypassing its prefix and `when@` guards).

### Added

- **UiKit page contract (REQ-UI-001 / REQ-UI-003).** The upload page is the UiKit form scaffold (`PdfEditorPages::upload()`
  view model) and the PDF workspace a unique page on the UiKit admin layout; both render inside
  `nowo_ui_kit.layouts.admin` and stack the editor assets with `parent()`. New `web_ui.layout_template` (default null)
  and `web_ui.templates.upload` / `.workspace`. `docs/TEMPLATES.md` and a `PageContractTestCase` test.
- **Twig overrides (REQ-TWIG-001).** `TwigPathsPass` registers `@NowoPdfEditorBundle` with
  `templates/bundles/NowoPdfEditorBundle/` first (plus `@!NowoPdfEditorBundle` for the vendor copy); documented
  subpaths, freeze rule and recovery.
- **Canonical access keys (REQ-UI-002).** `security.access_roles` and `security.access_checker` (custom
  `PdfEditorAccessCheckerInterface` service).
- Container build fails without SecurityBundle or with CSRF protection disabled (upload form / apply).

### Changed

- **Bundle class `NowoPdfEditorBundle`** (REQ-TWIG-002 / REQ-I18N-003): class name, Twig namespace
  `@NowoPdfEditorBundle`, override directory and translation domain now match. Public assets move to
  `/bundles/nowopdfeditor/` (the asset package follows the registered class); the editor build uses relative URLs.
- Requires Symfony 7.4 / 8.0 / 8.1 and `nowo-tech/ui-kit-bundle` ^2.17.3 (Symfony 6.4 and 7.0–7.3 dropped).
- The editor's private CSS classes are `nowo-pdf-editor-*` (were `nowo-ui-btn*`, `nowo-ui-input`, …) so they no longer
  restyle UiKit classes on the host page.
- An empty `security.access_roles` list without a custom checker fails the container build (it never grants anyone).
- `apply` rejects a declared `Content-Length` above the `max_upload_bytes` allowance before reading the body.

### Deprecated

- `Nowo\PdfEditorBundle\PdfEditorBundle` (use `NowoPdfEditorBundle`), the `@PdfEditor` Twig namespace,
  `security.roles` (use `security.access_roles`), `editor/layout.html.twig` (now a shim on the UiKit layout) and
  `editor/_toolbar.html.twig`; all removed in 2.0. See [UPGRADING](UPGRADING.md#unreleased).

### Template contract

- `editor/index.html.twig` extends `@NowoUiKit/page/form.html.twig` (variable `page`); the 1.x `nowo_pdf_editor_body`
  block of the upload page is gone (data blocks `nowo_ui_form_body`, `nowo_ui_form_aside`).
- `editor/workspace.html.twig` extends `layout ?? nowo_ui_layout.admin`; data blocks `nowo_pdf_editor_body`,
  `nowo_pdf_editor_styles`, `nowo_pdf_editor_scripts`; optional `nowo_pdf_editor_config` variable.

## [1.4.1] - 2026-10-28

PdfEditor: the stock editor layout links the compiled editor styles. See [UPGRADING](UPGRADING.md#to-141).

### Fixed

- **Stock layout stylesheet.** `editor/layout.html.twig` linked only the base `css/pdf-editor.css`, never the compiled
  editor styles `build/pdf-editor.css` (page rail, paginator, badges…); hosts using the bundle layout got a partly
  unstyled editor. Both are linked now (the demo override already did).

## [1.4.0] - 2026-10-28

Security: allow_unauthenticated is refused in production (prod or non-debug kernels). See [UPGRADING](UPGRADING.md#to-140).

### Security

- **`allow_unauthenticated` refused in production.** The switch opens the PDF editor to anonymous users; the
  container now refuses to compile with it in the `prod` environment or in any non-debug kernel outside `test*`
  environments (as ContactFormBundle). The demo keeps it in `dev` and requires `ROLE_ADMIN` in its production mode
  (`when@prod`).

## [1.3.1] - 2026-10-24

Bundle views render form bodies through the child loop (REQ-TWIG-003). See [UPGRADING](UPGRADING.md#to-131).

### Changed

- `editor/index.html.twig` renders the upload form body through the child loop (`{% for child in form %}` + `not child.rendered` + `form_row(child)`) instead of `form_row(form.pdf)`; the demo overrides follow the same pattern (REQ-TWIG-003). Same markup for the current `PdfUploadType`; new fields now render without overriding the template.

## [1.3.0] - 2026-10-21

Attribute routes, logger and clock, Python engine tests, release-check gates, demo timeouts, stale root trees removed. See [UPGRADING](UPGRADING.md#to-130).

### Added

- Python engine test suite (`tests/Python`, pytest + pytest-cov, 90% line gate) with `make test-python` and a CI `python` job (REQ-TEST-004).
- CI `frontend` job (TypeScript type check + Vite build) and `make test-ts`; `Demo smoke` workflow (tags, weekly, manual) (REQ-TEST-011).
- `WorkspaceManager` and `PythonPdfEngine` log workspace creation, purges and engine timeouts/failures through the `logger` service (no document data, names or paths) (REQ-OBS-001); the workspace TTL reads the `clock` service (REQ-DI-001).

### Changed

- Routes are `#[Route]` attributes on `EditorController` (`#[AsController]`); `Resources/config/routing.yaml` only imports them, so the host import is unchanged (REQ-SF-004 / REQ-SF-003).
- `twig/extra-bundle` and `twig/string-extra` are required; `psr/clock` and `psr/log` are explicit requirements (REQ-PKG-004 / REQ-TWIG-004).
- `release-check` runs sequentially from `ensure-up` and adds `check-twig-extra`, `check-no-raw-html-form`, `validate-translations` (new, key parity across the 7 locales), `test-python` and `test-ts` (REQ-MAKE-002 / REQ-MAKE-004).
- React sources use kebab-case file names (`pdf-editor-app.tsx`, `components/editor-shell.tsx`, …) (REQ-ASSETS-003); shipped build regenerated.
- Demo: FrankenPHP Caddyfiles set the documented timeout stack (PHP 90 s < Caddy write 100 s, `max_wait_time` 30 s) (REQ-RUNTIME-001); `release-check` refreshes the bundle first (REQ-DEMO-007); DNS block commented (REQ-DEMO-009).
- Docs: `docs/PSR.md` is the PdfEditor evaluation (it was a WhatsApp leftover) (REQ-CS-007); `docs/CONFIGURATION.md` Profiles and Translations sections (REQ-CFG-002 / REQ-I18N-001); `docs/SECURITY.md` Logging section.
- Cursor baseline: `.cursorignore` and the 00/10/20/40/50 rule files (REQ-IDE-001…004); `.gitignore` grouped with comments (REQ-GITIGNORE-001).

### Removed

- Stale root copies `assets/` and `editor/` (unused duplicates of `src/Resources/assets` and `src/Resources/views`) no longer ship in the package (REQ-PKG-004 / REQ-ASSETS-001).

### Fixed

- `.scripts/check-no-cursor-coauthor.sh` also runs inside git worktrees (`.git` file instead of directory).
- Docker images (bundle and demo) install PyMuPDF from 1.x binary wheels (`pymupdf>=1.24.0,<2`): PyMuPDF 2.0 has no musllinux wheel and the image build failed compiling it.

## [1.2.2] - 2026-10-20

Dependency refresh (bundle and demos), php-cs-fixer ParallelConfig(2). See [UPGRADING](UPGRADING.md#to-122).

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19); shipped assets rebuilt.
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Documentation: wave-1 REQ compliance — README standard badges, product blurb, canonical FrankenPHP claim with the committed banner image, full documentation list and Tests and coverage section; CONTRIBUTING Cursor Agent settings and post-release-commit trailer check; issue template renamed to `---feature-request.md`; `.cursor/mcp.json` (Engram) and versioned Spec Kit Cursor skills; full SPEC-KIT manual and SPEC-DRIVEN-DEVELOPMENT; DEMO-FRANKENPHP development / production / troubleshooting; doc TOCs; Spec Kit inventory rebuilt (69/69).

## [1.2.1] - 2026-10-13

### Fixed

- The upload form no longer renders `enctype="multipart/form-data"` twice on its `<form>` tag: the templates passed it in `form_start()` attrs although Symfony already adds it for the `FileType` field (bundle template and demo overrides).

## [1.2.0] - 2026-10-13

### Added

- `nowo:pdf-editor:purge --older-than=<age> [--dry-run]` deletes workspaces not modified for the given age (default 24h; seconds or `s`/`m`/`h`/`d`/`w`), so abandoned uploads no longer pile up in `workspace_dir`. Backed by `WorkspaceManager::purgeOlderThan()`.

### Deprecated

- Defining profiles other than `default_profile` under `nowo_pdf_editor.profiles`: they were validated but never used. A deprecation now names them; support is removed in 2.0.
- `Nowo\PdfEditorBundle\Exception\UnknownProfileException`: never thrown (the configuration rejects an unknown `default_profile`); removed in 2.0.

### Changed

- `symfony/deprecation-contracts` is now an explicit requirement (it was already installed through Symfony).
- `OperationDecoder::bindAddImagePathsToWorkspace()`: removed an unreachable duplicate "escapes the workspace" check; the decoder and `EditorController::apply()` error paths are now covered by tests (coverage gate back to 100%).

## [1.1.0] - 2026-10-13

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

[1.4.1]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.4.1
[1.4.0]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.4.0
[1.3.1]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.3.1
[1.3.0]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.3.0
[1.2.2]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.2.2
[1.2.1]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.2.1
[1.2.0]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.2.0
[1.1.0]: https://github.com/nowo-tech/PdfEditorBundle/releases/tag/v1.1.0
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
