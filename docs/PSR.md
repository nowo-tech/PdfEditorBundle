# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/pdf-editor-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for package sources and tests. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **Adopted** | `LoggerInterface` on Graph client, WhatsApp client, and webhook controller. Tokens and full phone numbers are never logged. |
| PSR-6 / PSR-16 Cache | **N/A** | No package-owned cache layer. |
| PSR-7 / PSR-17 HTTP messages | **N/A** | Symfony HttpFoundation is the webhook surface. |
| PSR-18 HTTP client | **N/A** | Symfony HttpClient (`HttpClientInterface`) is the outbound Graph client; a PSR-18 bridge would not help Symfony hosts. |
| PSR-11 Container | **N/A** | Constructor injection only. |
| PSR-14 Event dispatcher | **Adopted** | Symfony `EventDispatcherInterface` (PSR-14 compatible) for send/webhook events. |
| PSR-15 HTTP middleware | **N/A** | Symfony controller for webhooks. |
| PSR-20 Clock | **N/A** | No time-sensitive domain logic requiring a clock SPI. |

## Summary

- **Adopted beyond baseline:** PSR-3 Logger, PSR-14 Event dispatcher
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.
- **Re-evaluate** when the package gains cache, clock, or PSR-18 surfaces.

---

_REQ-CS-007 evaluation date: 2026-08-24._
