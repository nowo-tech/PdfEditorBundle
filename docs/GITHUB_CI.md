# GitHub Actions CI

## Workflows

- `ci.yml` — PHP × Symfony matrix, `composer audit`, coverage on PHP 8.2 + Symfony 7.4, git hygiene (REQ-GIT-001), `changes` job (light mode)
- `release.yml` — GitHub Release on `v*` tags
- `sync-releases.yml` — backfill missing releases
- `pr-lint.yml` — semantic PR titles
- `stale.yml` — stale issues/PRs

## REQ-GIT-001

The `git-hygiene` job in `ci.yml` runs `make check-no-cursor-coauthor` with `fetch-depth: 0` so the full history is scanned. CI fails if any commit reachable from `HEAD` contains Cursor co-author trailers. Install hooks with `make setup-hooks` before the first commit.

---

## Light mode for deps and docs-only PRs

Bundle PRs run a reduced CI when they only touch dependencies or documentation, or on every PR while the
organisation switch says so, so bursts of Dependabot / docs PRs do not
hold the organisation's runner pool for hours. Releases are not affected: they are tagged from a **push** run, which
always runs everything.

### Classification

The `changes` job of `ci.yml` sets `mode` (and writes the reason and the changed paths to the job summary). The
organisation Actions variable `NOWO_CI_PR_MODE` decides how pull requests are treated; a repository variable with
the same name overrides it for one repo:

| `NOWO_CI_PR_MODE` | Pull requests | push (main/master), tags, `workflow_dispatch`, `schedule` |
|-------------------|---------------|-------------------------------------------------------------|
| `light` | every PR is light, whatever it touches | always full |
| `auto`, unset or empty | light only when every changed path is `composer.json` / `composer.lock` (root or `demo/<app>/`), `docs/**`, `*.md`, `.github/ISSUE_TEMPLATE/**`, `specs/**` or `.cursor/**`; full otherwise | always full |
| `full` | every PR is full | always full |

In `auto` the job diffs the PR merge commit against its base parent (`git diff --name-only --no-renames HEAD^1 HEAD`,
no third-party action). It is fail-closed: jobs are only reduced on `mode == 'light'`, so an error, an unknown
variable value or an empty output means a full run.

**Switching back to full CI is a one-place change**: set the organisation variable `NOWO_CI_PR_MODE` to `auto`
(path-based) or `full` (Settings → Secrets and variables → Actions → Variables, or
`gh variable set NOWO_CI_PR_MODE --org nowo-tech --body full`). No workflow edit is needed. One repo can opt out
with a repository variable of the same name (`gh variable set NOWO_CI_PR_MODE -R nowo-tech/<repo> --body full`).

### What still runs in light mode

- The newest PHP × newest Symfony cell of the test matrix (SQLite): `composer validate --strict`, install, `composer
  audit`, the whole PHPUnit suite.
- PHPStan, Code Style Check, Dependency floor (where the repo has it), Git history and the semantic PR title.

Skipped or reduced in light mode in this repo: the Python engine tests, the Frontend build check, Igor and the other PHP x Symfony cells (echo step only).

The other matrix cells still start and report success after one echo step: a matrix job skipped with a job-level
`if:` reports its un-expanded name (`PHP ${{ matrix.php }} - …`), so the required contexts `PHP x - Symfony y` would
never appear and the PR would stay blocked. Matrix jobs with service containers also pass an empty service `image` in
light mode, so no database container is pulled. Non-matrix jobs (coverage, databases, demo smoke, …) are skipped with
a job-level `if:`: a skipped job reports its own name with a successful conclusion for branch protection. Workflows
without any required check may use `paths-ignore` with the same list plus a job-level
`if: ${{ github.event_name != 'pull_request' || vars.NOWO_CI_PR_MODE != 'light' }}` instead (never on a workflow
that holds a required check: a path-filtered workflow leaves its checks pending).

### Concurrency

PR workflows declare:

```yaml
concurrency:
  group: ${{ github.workflow }}-${{ github.event.pull_request.number || github.run_id }}
  cancel-in-progress: ${{ github.event_name == 'pull_request' }}
```

A new push to a PR cancels its superseded run. Push runs use their own `run_id` group: a shared group (for example
`github.ref`) would cancel a *pending* main run when a third one queues, even with `cancel-in-progress: false`, and
`tag_when_green.sh` would then find no green run for that release commit.
