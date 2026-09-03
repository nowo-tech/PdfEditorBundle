# GitHub Actions CI

## Workflows

- `ci.yml` — PHP × Symfony matrix, `composer audit`, coverage on PHP 8.2 + Symfony 7.4, git hygiene (REQ-GIT-001)
- `release.yml` — GitHub Release on `v*` tags
- `sync-releases.yml` — backfill missing releases
- `pr-lint.yml` — semantic PR titles
- `stale.yml` — stale issues/PRs

## REQ-GIT-001

The `git-hygiene` job in `ci.yml` runs `make check-no-cursor-coauthor` with `fetch-depth: 0` so the full history is scanned. CI fails if any commit reachable from `HEAD` contains Cursor co-author trailers. Install hooks with `make setup-hooks` before the first commit.
