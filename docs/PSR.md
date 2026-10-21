# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/pdf-editor-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Table of contents

- [Baseline (always)](#baseline-always)
- [Interface / contract PSRs](#interface--contract-psrs)
- [Summary](#summary)

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for package sources and tests. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **Adopted** | `WorkspaceManager` (workspace creation, TTL purge) and `PythonPdfEngine` (engine timeouts / failures) take an optional `Psr\Log\LoggerInterface` (the `logger` service); no document data, file names or tokens are logged. `psr/log` in `require`. |
| PSR-6 / PSR-16 Cache | **N/A** | Workspaces live on the filesystem (`workspace_dir`); rendered page images are files next to the PDF, not a cache pool. |
| PSR-7 / PSR-17 HTTP messages | **N/A** | Symfony HttpFoundation controllers (upload, inspect, render, apply, download). |
| PSR-18 HTTP client | **N/A** | No outbound HTTP; the only external process is the optional Python engine (Symfony Process, REQ-RUNTIME-001). |
| PSR-11 Container | **N/A** | Constructor injection only. |
| PSR-14 Event dispatcher | **N/A** | No PHP extension events; the editor talks to the browser through JSON endpoints. |
| PSR-15 HTTP middleware | **N/A** | No middleware. |
| PSR-20 Clock | **Adopted** | `WorkspaceManager::purgeOlderThan()` takes the TTL reference time from an optional `Psr\Clock\ClockInterface` (the `clock` service). `psr/clock` in `require`. |

## Summary

- **Adopted beyond baseline:** PSR-3 (logging), PSR-20 (clock).
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.
- **Re-evaluate** when the package gains HTTP clients, caches or extension events.

---

_REQ-CS-007 evaluation date: 2026-10-21._
