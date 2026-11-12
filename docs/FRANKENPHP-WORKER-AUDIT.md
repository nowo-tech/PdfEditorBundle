# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/pdf-editor-bundle` (`symfony-bundle`) |
| Audited revision | `v1.0.2` |
| Reviewed version | `v1.6.0` (re-checked 2026-11-12 against the kept-alive test below; `git diff v1.6.0 -- src` is empty at `bcb5e43`) |
| Audit date | 2026-11-12 (earlier audit date 2026-09-24 kept in the history) |
| Method | Manual review of every PHP file under `src/` (controller, services, command, forms, DI extension, configuration, `Resources/config/services.yaml`); PHPStan classic + worker + hardening |
| **Verdict** | ✅ **100% compatible** with FrankenPHP worker mode and **`reset_kernel: false`** (scenario B below) |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Services reviewed](#services-reviewed)
- [Findings](#findings)
  - [W-01 — Python engine calls block a worker thread for up to the configured timeout (Low)](#w-01--python-engine-calls-block-a-worker-thread-for-up-to-the-configured-timeout-low)
  - [W-02 — Workspaces are never removed (Low)](#w-02--workspaces-are-never-removed-low)
  - [W-03 — Workspace isolation is by owner and unguessable id (Info; updated 2026-11-12)](#w-03--workspace-isolation-is-by-owner-and-unguessable-id-info-updated-2026-11-12)
- [Usage recommendations in worker mode](#usage-recommendations-in-worker-mode)
- [Kept-alive test (REQ-RUNTIME-002)](#kept-alive-test-req-runtime-002)
- [Re-audit triggers](#re-audit-triggers)

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

Symfony Runtime maps `FRANKENPHP_RESET_KERNEL` (default unset → **false**) into whether the kernel is rebooted between worker requests. The Symfony 8 demo leaves it unset, so it exercises **scenario B**.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | All services only hold `readonly` constructor dependencies; `EditorProfile` is `final readonly`; access checkers are `final readonly` |
| Static properties / `static` locals | ✅ | None; only static factory methods on exceptions |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | Nothing to reset |
| Request / user / locale captured in services | ✅ | `Request` is a controller argument; the user is read from `TokenStorageInterface` on every call (`EditorController::assertAccess()`) |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; config is compiled into the `nowo_pdf_editor.profile` definition |
| Doctrine / EntityManager | ✅ N/A | No persistence; workspaces live on the filesystem |
| Output, headers, `exit`, shutdown functions | ✅ | None; responses are built with HttpFoundation objects |
| Resources (files, sockets, cURL) held open | ✅ | Lock handle closed in `finally`; temp ops file unlinked in `finally`; page PNG locks deleted with page images |
| Memory growth across requests | ✅ | No caches or accumulating arrays |
| Blocking I/O and timeouts | ⚠️ Low | Python engine runs via `Process` with explicit, configurable `timeout` (60 s) and `idle_timeout` (30 s) |
| Third-party static state | ✅ | Only Symfony Process / Form / Twig / Security, used per call; PyMuPDF runs in a child process |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` + `ruleset-hardening.neon` in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` declares a `worker` block and `demo/symfony8/docker/entrypoint.sh` defaults to `FRANKENPHP_MODE=worker`.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Nowo\PdfEditorBundle\Controller\EditorController` | yes (controller service) | none (`readonly` dependencies) | ✅ | ✅ |
| `nowo_pdf_editor.profile` (`Config\EditorProfile`) | yes | none (`final readonly` config) | ✅ | ✅ |
| `nowo_pdf_editor.engine` (`Engine\PythonPdfEngine`) | yes | none (`readonly` runner + profile) | ✅ | ✅ |
| `nowo_pdf_editor.process_runner` (`Engine\SymfonyProcessRunner`) | yes | none; a new `Process` per call | ✅ | ✅ |
| `nowo_pdf_editor.workspace_manager` (`Document\WorkspaceManager`) | yes | none (`readonly` profile) | ✅ | ✅ |
| `nowo_pdf_editor.access_checker` (`RolePdfEditorAccessChecker` or `AllowAllPdfEditorAccessChecker`) | yes | none (`final readonly`; asks `AuthorizationChecker` per call) | ✅ | ✅ |
| `nowo_pdf_editor.workspace_owner_resolver` (`Security\WorkspaceOwnerResolver`) | yes | none (`final readonly`; user from `TokenStorageInterface`, anonymous token in the current request's session, read per call) | ✅ | ✅ |
| `Operation\OperationDecoder` | yes | none (only a class constant) | ✅ | ✅ |
| `Command\CheckEngineCommand` | yes (CLI only) | none | ✅ | ✅ |
| 2 form types (`PdfUploadType`, `EditorTextType`) | yes | stateless | ✅ | ✅ |

Value objects (`Workspace`, `EditorOperation`, `ProcessOutcome`) are `readonly`, created per call and never stored in a service.

## Findings

### W-01 — Python engine calls block a worker thread for up to the configured timeout (Low)

- **Where:** `src/Engine/SymfonyProcessRunner.php` (`Process::run()`), called from `src/Engine/PythonPdfEngine.php`; defaults in `src/DependencyInjection/Configuration.php` (`timeout` 60 s, `idle_timeout` 30 s). Reached from `EditorController::inspect()`, `pageImage()` and `apply()` when `engine_mode: python`.
- **Worker impact:** each render/inspect/apply pins one PHP worker thread while PyMuPDF runs. `pageImage()` additionally waits on an exclusive `flock()` when several requests render the same page. Timeouts are explicit and configurable and the process is stopped on timeout, so no state leaks; the risk is thread starvation under load. With the default `engine_mode: client` these paths are not used.
- **Recommendation:** lower `timeout` / `idle_timeout` for interactive use, size `num_threads` / worker count for concurrent renders, and cap queued requests with FrankenPHP `max_wait_time`.

### W-02 — Workspaces are never removed (Low)

- **Where:** `src/Document/WorkspaceManager.php` creates `<workspace_dir>/<id>/` directories. Default `workspace_dir` is `sys_get_temp_dir()/nowo-pdf-editor` (`Configuration.php`).
- **Worker impact:** no in-memory growth, but disk usage grows with every upload for the lifetime of the host/container. In a long-lived worker container nothing restarts the temp directory. Page PNG lock files are removed together with page images after apply (1.0.2).
- **Recommendation:** run a periodic cleanup (cron / scheduled command) that deletes workspace directories older than a TTL, or put `workspace_dir` on a volume with a cleanup policy.

### W-03 — Workspace isolation is by owner and unguessable id (Info; updated 2026-11-12)

- **Where:** `WorkspaceManager` uses `bin2hex(random_bytes(16))` for the id and stores the creator in `owner.txt`; `WorkspaceOwnerResolver` resolves the owner per call (authenticated user, or a random token kept in the caller's session). The 2026-09-24 review described isolation by id only.
- **Worker impact:** none specific to worker mode — nothing is cached in memory, so one user never sees another user's workspace through leaked service state, and a workspace of another owner is a plain 404. Covered by the kept-alive test below.
- **Recommendation:** none; stateless firewalls with a custom access checker must opt in to `allow_ownerless_workspaces` (such workspaces are reachable by id only).

No other findings. The client-PDF upload path (`EditorController::applyClientPdf()`) reads the whole body into memory per request; it is freed at the end of the request and does not accumulate.

## Usage recommendations in worker mode

- No reset hook or special configuration is needed; the bundle behaves the same under scenarios A and B.
- Keep `FRANKENPHP_RESET_KERNEL` unset or `false` for maximum throughput; this bundle is designed for that mode.
- Keep the default `engine_mode: client` when possible; with `python`, tune `timeout` / `idle_timeout` and the worker thread count (W-01).
- Schedule cleanup of `workspace_dir` (W-02).
- Custom `PdfEditorAccessCheckerInterface`, `ProcessRunnerInterface` or `PdfEngineInterface` implementations must stay stateless (or implement `ResetInterface`); do not cache the current user, request or workspace in a property.

## Kept-alive test (REQ-RUNTIME-002)

Test: `Nowo\PdfEditorBundle\Tests\Integration\Worker\WorkerKeptAliveKernelTest` (`tests/Integration/Worker/WorkerKeptAliveKernelTest.php`, host kernel `WorkerTestKernel`). It boots **one** kernel with the editor routes imported and drives `http_kernel` `handle()` + `terminate()` 1,200 times, alternating two anonymous users (two sessions, each owning one workspace): every iteration loads the caller's own workspace page, downloads the caller's own PDF and requests the other user's workspace id. `services_resetter` / `kernel.reset` is **never** called: requests are sent through the `http_kernel` service itself, because `Kernel::handle()` runs the services resetter on the next boot after a `terminate()`. Each response must contain only the caller's own file name / bytes, the other user's workspace must always be a 404, and memory growth after a 100-iteration warm-up must stay below 4 MiB.

Result: no state is carried between requests (the owner is resolved from the current session on every call), so the bundle is **safe with kernel reset false**.

The test kernel is `debug: false` (the `test` environment is accepted by `allow_unauthenticated`): in a debug kernel Twig's profiler keeps one profile per render for the life of the process, which is a development-only host setting and not bundle state.

Rule 7: the bundle has no persistence layer and never clears, flushes or resets the host's default EntityManager (workspaces live on the filesystem).

## Re-audit triggers

Re-run this audit when a change adds: properties to `EditorController`, `PythonPdfEngine`, `WorkspaceManager` or the access checkers; an in-memory cache of workspaces, page images or engine results; a persistent/long-running engine process; an event listener; or any use of `$_SERVER` / `$_ENV` at runtime.
