# Spec-driven development — PdfEditorBundle

In this repository, **spec-driven development** has three layers that stay in sync:

1. **GitHub Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) ([`spec.md`](../specs/001-baseline/spec.md), [`code-inventory.md`](../specs/001-baseline/code-inventory.md)), initialized with [GitHub Spec Kit](https://github.com/github/spec-kit) (`.specify/`, **Cursor Agent** skills in `.cursor/skills/speckit-*`). The inventory maps **100%** of the tracked files under `src/`. **How to install, initialize, and use Spec Kit:** [`SPEC-KIT.md`](SPEC-KIT.md).
2. **Product behavior** — what **PdfEditorBundle** guarantees to applications that integrate it (see [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`INSTALLATION.md`](INSTALLATION.md)). **PHPUnit** and **PHPStan** (classic + worker + hardening FrankenPHP rulesets) enforce the PHP contracts in CI.
3. **Traceability anchors** — stable **`REQ-*`** identifiers in Makefiles and demos so changes to scripts, ports and demo workflows stay discoverable from issues and PRs.

There is no separate executable spec language (for example Gherkin); Spec Kit specs, tests and static analysis are the mechanical proof alongside this document.

## Table of contents

- [User stories](#user-stories)
- [Bundle functional scope](#bundle-functional-scope)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Suggested workflow for contributors](#suggested-workflow-for-contributors)
- [Relationship to Engram](#relationship-to-engram)
- [GitHub Spec Kit](#github-spec-kit)
- [See also](#see-also)

## User stories

| ID | Story | Integrator docs |
| --- | --- | --- |
| US-01 | **As a** user, **I want** to upload a PDF and open it in an editor workspace **so that** I can change it without desktop software. | [USAGE.md](USAGE.md) |
| US-02 | **As a** user, **I want** to edit text, form fields, pages, notes and watermarks in the browser and save the rewritten PDF **so that** the document stays a PDF. | [USAGE.md](USAGE.md) |
| US-03 | **As an** integrator, **I want** an optional server-side Python (PyMuPDF) engine with bounded timeouts **so that** heavy operations do not run in the browser. | [CONFIGURATION.md](CONFIGURATION.md) |
| US-04 | **As an** integrator, **I want** role-based access and per-owner workspaces **so that** one user cannot open another user's document. | [CONFIGURATION.md](CONFIGURATION.md) |
| US-05 | **As an** operator, **I want** to purge abandoned workspaces **so that** uploads do not pile up on disk. | [USAGE.md](USAGE.md) |
| US-06 | **As an** operator, **I want** the bundle to run in FrankenPHP worker mode without kernel reset **so that** I keep worker performance. | [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) |

The baseline spec ([`spec.md`](../specs/001-baseline/spec.md)) carries the acceptance criteria (Given/When/Then) and the `FR-*` requirements.

## Bundle functional scope

**Goal:** a standalone Symfony PDF editor workspace: upload a PDF, edit it in a React editor (embedpdf + pdf-lib) and store the rewritten bytes; optionally rewrite it server side with the Python/PyMuPDF engine.

**In scope**

- Workspace lifecycle under `workspace_dir` (upload, open, replace, page images, download) and the `nowo:pdf-editor:purge` TTL command.
- Client engine (default `engine_mode: client`) and the optional Python engine (`engine_mode: python`, Symfony Process with `timeout` / `idle_timeout`), plus `nowo:pdf-editor:check-engine`.
- Access control (`security.*` roles, allow-all for demos only) and workspace ownership (user identifier or anonymous session token).
- Editor Twig templates, translations (`en`, `es`, `de`, `fr`, `it`, `nl`, `pt`) and the committed Vite build under `Resources/public/build/`.

**Explicit non-goals**

- OCR for scanned pages.
- Signing workflows (they belong to PdfSignableBundle; this bundle is not an extension of it).
- Scheduling the purge: the command exists, the host decides when it runs.
- `demo/` trees: illustrative only, not part of the Packagist API.

## Validating the functional spec

- **`make release-check`** (Docker): git hygiene, open PRs, CS, Rector, PHPStan, coverage, demo smoke.
- **`composer qa`** / **`make qa`**: coding standard + PHPUnit; **`composer phpstan`** for static analysis.
- **Coverage floor:** `composer coverage-check` (`composer test-coverage-100`) fails under **100%** line coverage of `src/` PHP.
- Behavior changes require tests under `tests/` (PHPUnit `Unit` and `Integration` suites); see [TESTING.md](TESTING.md).

## Requirement identifiers (`REQ-*`)

| ID | Location | Meaning |
| --- | --- | --- |
| `REQ-MAKE-010` | Root `Makefile` | Compose V2 with V1 fallback; absolute docker path. |
| `REQ-CS-008` | Root `Makefile` (`igor`) | Igor FrankenPHP worker-state audit. |
| `REQ-TEST-011` | Root `Makefile` (`demo-smoke`) | Boot the demos and assert HTTP 200. |
| `REQ-GIT-001` | Root `Makefile` (`setup-hooks`, `check-no-cursor-coauthor`) | No AI co-author trailers in commits. |
| `REQ-REL-003` | Root `Makefile` (`check-open-prs`) | Release blocked while unresolved PRs exist. |
| `REQ-MAKE-008` / `REQ-MAKE-009` | `demo/Makefile` | Aggregate demo dependency updates; optional monorepo helper. |

When scripted or demo behavior changes, update the comment next to the existing `REQ-*` or add a new ID and list it here.

## Suggested workflow for contributors

1. **Clarify behavior** in an issue or draft PR: acceptance criteria for the product and, if relevant, the Makefiles/demos (`REQ-*`).
2. **Implement** with tests and static analysis.
3. **Anchor scripts and demos** when dev UX changes: add or adjust `REQ-*` comments and the table above.
4. **Ship integrator docs** when behavior or configuration changes: [`USAGE.md`](USAGE.md), [`CONFIGURATION.md`](CONFIGURATION.md), [`CHANGELOG.md`](CHANGELOG.md), and [`UPGRADING.md`](UPGRADING.md) when consumers must change code or config.
5. **Keep Spec Kit artifacts in sync** when files under `src/` change: update [`spec.md`](../specs/001-baseline/spec.md) and [`code-inventory.md`](../specs/001-baseline/code-inventory.md) and follow the maintainer checklist in [`SPEC-KIT.md`](SPEC-KIT.md).

## Relationship to Engram

[ENGRAM.md](ENGRAM.md) covers the developer's **local, cross-session memory** (MCP, configured in `.cursor/mcp.json`). This document and `specs/` are the **committed, reviewable** source of truth. Never store product decisions only in Engram; summarize them here or in the baseline spec.

## GitHub Spec Kit

This repository uses [GitHub Spec Kit](https://github.com/github/spec-kit) with **Cursor Agent** (`cursor-agent` integration).

| Artifact | Path |
| --- | --- |
| **Operator manual** (install, init, usage) | [`SPEC-KIT.md`](SPEC-KIT.md) |
| Baseline spec | [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) |
| Code inventory (100%) | [`specs/001-baseline/code-inventory.md`](../specs/001-baseline/code-inventory.md) |
| Constitution | [`.specify/memory/constitution.md`](../.specify/memory/constitution.md) |
| Cursor Agent skills | [`.cursor/skills/`](../.cursor/skills/) (`speckit-*`) |

Re-initialize (maintainers): `specify init --here --force --integration cursor-agent --script sh`, then `specify integration list` (Cursor → installed, default). Start a new feature in Cursor Agent with `/speckit-specify <description>`.

## See also

- [GitHub Spec Kit manual](SPEC-KIT.md) and [baseline spec](../specs/001-baseline/spec.md)
- [Usage](USAGE.md), [Configuration](CONFIGURATION.md), [Installation](INSTALLATION.md)
- [Contributing](CONTRIBUTING.md), [Release](RELEASE.md), [Testing](TESTING.md)
- [Demo with FrankenPHP](DEMO-FRANKENPHP.md), [FrankenPHP worker audit](FRANKENPHP-WORKER-AUDIT.md)
- [Engram](ENGRAM.md)
