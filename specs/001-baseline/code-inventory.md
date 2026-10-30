# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/pdf-editor-bundle`  
**Last audited**: 2026-10-30 (audit: `git ls-files src | wc -l` → 74)

Paths are relative to `src/`. Every tracked file is listed; Vite outputs under `Resources/public/build/` are rows of **Build outputs** (FR-BUILD-001), produced from `Resources/assets/src/main.tsx`.

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/routing.yaml` | Route imports | FR-DI-001 |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Compiler/CsrfProtectionPass.php` | CSRF fail-closed check | FR-SEC-003 |
| `DependencyInjection/Compiler/SecurityBundlePass.php` | SecurityBundle check | FR-SEC-003 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace + overrides | FR-TWIG-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/PdfEditorExtension.php` | DI extension | FR-CFG-002 |
| `NowoPdfEditorBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `PdfEditorBundle.php` | Deprecated bundle alias | FR-BUNDLE-001 |
| `View/PdfEditorPages.php` | UiKit view models | FR-UI-001 |

## Config VO

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Config/EditorProfile.php` | Compiled profile | FR-CFG-001, FR-WORKER-001 |

## Document

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Document/Workspace.php` | Workspace VO | FR-DOC-001, FR-WORKER-001 |
| `Document/WorkspaceManager.php` | Workspace FS ops | FR-DOC-001, FR-WORKER-001 |

## Engine

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Engine/PdfEngineInterface.php` | Engine contract | FR-ENG-001 |
| `Engine/ProcessOutcome.php` | Process result VO | FR-ENG-001, FR-WORKER-001 |
| `Engine/ProcessRunnerInterface.php` | Process runner contract | FR-ENG-001 |
| `Engine/PythonPdfEngine.php` | PyMuPDF engine | FR-ENG-001, FR-ENG-002, FR-WORKER-001 |
| `Engine/SymfonyProcessRunner.php` | Symfony Process runner | FR-ENG-001, FR-ENG-002, FR-WORKER-001 |

## Operations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Operation/EditorOperation.php` | Op VO | FR-OPS-001, FR-WORKER-001 |
| `Operation/OperationDecoder.php` | JSON → ops | FR-OPS-001, FR-WORKER-001 |

## Controller & forms

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/EditorController.php` | HTTP UI / API | FR-CTRL-001, FR-WORKER-001 |
| `Form/EditorTextType.php` | Text form | FR-FORM-001, FR-WORKER-001 |
| `Form/PdfUploadType.php` | Upload form | FR-FORM-001, FR-WORKER-001 |

## Security

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Security/AllowAllPdfEditorAccessChecker.php` | Demo allow-all | FR-SEC-001, FR-WORKER-001 |
| `Security/PdfEditorAccessCheckerInterface.php` | Access contract | FR-SEC-001 |
| `Security/RolePdfEditorAccessChecker.php` | Role checker | FR-SEC-001, FR-WORKER-001 |
| `Security/WorkspaceOwnerResolver.php` | User / anonymous-token owner | FR-SEC-002, FR-WORKER-001 |
| `Security/WorkspaceOwnerResolverInterface.php` | Workspace owner contract | FR-SEC-002 |

## CLI & exceptions

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/CheckEngineCommand.php` | Engine doctor | FR-CLI-001, FR-WORKER-001 |
| `Command/PurgeWorkspacesCommand.php` | Workspace TTL purge | FR-CLI-002 |
| `Exception/AccessDeniedException.php` | Access denied | FR-SEC-001 |
| `Exception/DocumentException.php` | Document errors | FR-DOC-001 |
| `Exception/EngineException.php` | Engine errors | FR-ENG-001 |
| `Exception/PdfEditorException.php` | Base exception | FR-CTRL-001 |
| `Exception/UnknownProfileException.php` | Unknown profile | FR-CFG-001 |

## Twig views

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/editor/_toolbar.html.twig` | Editor template _toolbar.html.twig | FR-TWIG-001 |
| `Resources/views/editor/index.html.twig` | Editor template index.html.twig | FR-TWIG-001 |
| `Resources/views/editor/layout.html.twig` | Editor template layout.html.twig | FR-TWIG-001 |
| `Resources/views/editor/workspace.html.twig` | Editor template workspace.html.twig | FR-TWIG-001 |

## Translations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoPdfEditorBundle.de.yaml` | Locale de | FR-I18N-001 |
| `Resources/translations/NowoPdfEditorBundle.en.yaml` | Locale en | FR-I18N-001 |
| `Resources/translations/NowoPdfEditorBundle.es.yaml` | Locale es | FR-I18N-001 |
| `Resources/translations/NowoPdfEditorBundle.fr.yaml` | Locale fr | FR-I18N-001 |
| `Resources/translations/NowoPdfEditorBundle.it.yaml` | Locale it | FR-I18N-001 |
| `Resources/translations/NowoPdfEditorBundle.nl.yaml` | Locale nl | FR-I18N-001 |
| `Resources/translations/NowoPdfEditorBundle.pt.yaml` | Locale pt | FR-I18N-001 |

## Frontend sources (React / TS)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/pdf-editor-app.tsx` | Editor app root | FR-FE-001 |
| `Resources/assets/src/components/editor-shell.tsx` | UI component editor-shell.tsx | FR-FE-001 |
| `Resources/assets/src/components/editor-toolbar.tsx` | UI component editor-toolbar.tsx | FR-FE-001 |
| `Resources/assets/src/components/page-rail.tsx` | UI component page-rail.tsx | FR-FE-001 |
| `Resources/assets/src/components/properties-panel.tsx` | UI component properties-panel.tsx | FR-FE-001 |
| `Resources/assets/src/components/scenario-banner.tsx` | UI component scenario-banner.tsx | FR-FE-001 |
| `Resources/assets/src/components/viewer-skeleton.tsx` | UI component viewer-skeleton.tsx | FR-FE-001 |
| `Resources/assets/src/components/viewport-chrome.tsx` | UI component viewport-chrome.tsx | FR-FE-001 |
| `Resources/assets/src/components/icons.tsx` | UI component icons.tsx | FR-FE-001 |
| `Resources/assets/src/embedpdf-bridge.ts` | embedpdf bridge | FR-FE-003 |
| `Resources/assets/src/engines/client-engine.ts` | Client PDF engine | FR-FE-002 |
| `Resources/assets/src/engines/vensas/content-stream.ts` | vensas content-stream / XObject helper | FR-FE-002 |
| `Resources/assets/src/engines/vensas/page-xobjects.ts` | vensas content-stream / XObject helper | FR-FE-002 |
| `Resources/assets/src/main.tsx` | Entry point | FR-FE-001 |
| `Resources/assets/src/object-selection-bridge.ts` | embedpdf bridge | FR-FE-003 |
| `Resources/assets/src/op-labels.ts` | Operation labels | FR-FE-001 |
| `Resources/assets/src/redaction-bridge.ts` | embedpdf bridge | FR-FE-003 |
| `Resources/assets/src/selection-bridge.ts` | embedpdf bridge | FR-FE-003 |
| `Resources/assets/src/styles/editor.css` | Editor styles | FR-FE-001 |
| `Resources/assets/src/types.ts` | Shared types | FR-FE-001 |

## Static assets

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/css/pdf-editor.css` | Layout CSS | FR-FE-004 |
| `Resources/public/js/pdf-editor.js` | Legacy JS shim | FR-FE-004 |

## Build outputs (Vite)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/build/chunks/browser-BKLM0ThC-BCLQGPD6-BWnWt4uP.js` | Output of `main.tsx` | FR-BUILD-001 |
| `Resources/public/build/chunks/direct-engine-DVLMOJWp-uJ1WMVjk.js` | Output of `main.tsx` | FR-BUILD-001 |
| `Resources/public/build/chunks/worker-engine-BCCrxWYm-CM6XALFo.js` | Output of `main.tsx` | FR-BUILD-001 |
| `Resources/public/build/pdf-editor.css` | Output of `main.tsx` | FR-BUILD-001 |
| `Resources/public/build/pdf-editor.js` | Output of `main.tsx` | FR-BUILD-001 |
| `Resources/public/build/pdf-editor.wasm` | Output of `main.tsx` | FR-BUILD-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Symfony config | 2 | 2 |
| Bundle & DI | 8 | 8 |
| Config VO | 1 | 1 |
| Document | 2 | 2 |
| Engine | 5 | 5 |
| Operations | 2 | 2 |
| Controller & forms | 3 | 3 |
| Security | 5 | 5 |
| CLI & exceptions | 7 | 7 |
| Twig views | 4 | 4 |
| Translations | 7 | 7 |
| Frontend sources (React / TS) | 20 | 20 |
| Static assets | 2 | 2 |
| Build outputs (Vite) | 6 | 6 |
| **Total production sources** | **74** | **74** |

## FrankenPHP worker evidence (not inventory rows)

| Artifact | Spec section | Requirement IDs |
| --- | --- | --- |
| `docs/FRANKENPHP-WORKER-AUDIT.md` | Worker audit | FR-WORKER-001, US-05 |
| `demo/symfony8/docker/frankenphp/Caddyfile` | Worker demo | FR-WORKER-001 |
| `phpstan.neon.dist` (classic + worker + hardening) | Static worker rules | FR-WORKER-001, SC-003 |
