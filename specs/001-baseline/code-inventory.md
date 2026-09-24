# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/pdf-editor-bundle`  
**Last audited**: 2026-09-24

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service wiring | FR-DI-001 |
| `Resources/config/routing.yaml` | Route imports | FR-DI-001 |

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `PdfEditorBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/PdfEditorExtension.php` | DI extension | FR-CFG-002 |

## Config VO

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Config/EditorProfile.php` | Compiled profile | FR-CFG-001 / FR-WORKER-001 |

## Document

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Document/Workspace.php` | Workspace VO | FR-DOC-001 / FR-WORKER-001 |
| `Document/WorkspaceManager.php` | Workspace FS ops | FR-DOC-001 / FR-WORKER-001 |

## Engine

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Engine/PdfEngineInterface.php` | Engine contract | FR-ENG-001 |
| `Engine/PythonPdfEngine.php` | PyMuPDF engine | FR-ENG-001 / FR-ENG-002 / FR-WORKER-001 |
| `Engine/ProcessRunnerInterface.php` | Process runner contract | FR-ENG-001 |
| `Engine/SymfonyProcessRunner.php` | Symfony Process runner | FR-ENG-001 / FR-ENG-002 / FR-WORKER-001 |
| `Engine/ProcessOutcome.php` | Process result VO | FR-ENG-001 / FR-WORKER-001 |

## Operations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Operation/EditorOperation.php` | Op VO | FR-OPS-001 / FR-WORKER-001 |
| `Operation/OperationDecoder.php` | JSON → ops | FR-OPS-001 / FR-WORKER-001 |

## Controller & forms

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/EditorController.php` | HTTP UI / API | FR-CTRL-001 / FR-WORKER-001 |
| `Form/PdfUploadType.php` | Upload form | FR-FORM-001 / FR-WORKER-001 |
| `Form/EditorTextType.php` | Text form | FR-FORM-001 / FR-WORKER-001 |

## Security

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Security/PdfEditorAccessCheckerInterface.php` | Access contract | FR-SEC-001 |
| `Security/RolePdfEditorAccessChecker.php` | Role checker | FR-SEC-001 / FR-WORKER-001 |
| `Security/AllowAllPdfEditorAccessChecker.php` | Demo allow-all | FR-SEC-001 / FR-WORKER-001 |

## CLI & exceptions

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/CheckEngineCommand.php` | Engine doctor | FR-CLI-001 / FR-WORKER-001 |
| `Exception/PdfEditorException.php` | Base exception | FR-CTRL-001 |
| `Exception/AccessDeniedException.php` | Access denied | FR-SEC-001 |
| `Exception/DocumentException.php` | Document errors | FR-DOC-001 |
| `Exception/EngineException.php` | Engine errors | FR-ENG-001 |
| `Exception/UnknownProfileException.php` | Unknown profile | FR-CFG-001 |

## Twig & i18n

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/editor/*.html.twig` | Editor shells | FR-TWIG-001 |
| `Resources/translations/NowoPdfEditorBundle.*.yaml` | Locales | FR-I18N-001 |

## Frontend (built assets)

| Source path | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/**` | React editor sources | FR-FE-001 |
| `Resources/public/build/**` | Built editor bundle | FR-FE-001 |
| `Resources/public/css/pdf-editor.css` | Legacy CSS | FR-FE-001 |
| `Resources/public/js/pdf-editor.js` | Legacy JS shim | FR-FE-001 |

## FrankenPHP

| Artifact | Spec section | Requirement IDs |
| --- | --- | --- |
| `docs/FRANKENPHP-WORKER-AUDIT.md` | Worker audit | FR-WORKER-001 / US-05 |
| `demo/symfony8/docker/frankenphp/Caddyfile` | Worker demo | FR-WORKER-001 |
| `phpstan.neon.dist` (classic + worker + hardening) | Static worker rules | FR-WORKER-001 / SC-003 |
