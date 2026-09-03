# Release

## Checklist

1. `make setup-hooks` (once per clone).
2. `make release-check` (includes `check-no-cursor-coauthor`, CS, Rector, PHPStan, coverage, demo smoke).
3. Run `make check-no-cursor-coauthor` again before `git push` after tagging (REQ-GIT-001).
3. Update [CHANGELOG.md](CHANGELOG.md) and [UPGRADING.md](UPGRADING.md) if needed.
4. Tag `vX.Y.Z` and push the tag (`release.yml` creates the GitHub Release).

Do not add Cursor co-author trailers (REQ-GIT-001).
