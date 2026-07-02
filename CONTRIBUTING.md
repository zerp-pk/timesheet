# Contributing

Thanks for considering contributing to `zerp/timesheet`.

## Getting started

1. Fork the repo and clone your fork.
2. Create a branch off `main`/`master`: `git checkout -b fix/short-description`.
3. Make your change.
4. Run the checks locally before pushing:
   ```bash
   composer validate --strict
   find src -name '*.php' -print0 | xargs -0 -n1 php -l
   ```
5. Commit with a clear, descriptive message and open a pull request.

## Pull requests

- Keep PRs focused on a single change; unrelated fixes should be separate PRs.
- Describe *why* the change is needed, not just what changed.
- Make sure CI passes before requesting review.

## Reporting bugs

Please use the bug report issue template and include steps to reproduce, the
Zerp/PHP/Laravel versions involved, and any relevant error output.

## Code of Conduct

This project follows the [Code of Conduct](CODE_OF_CONDUCT.md). By
participating, you agree to abide by it.

## Security issues

Do not report security vulnerabilities via public issues — see
[SECURITY.md](SECURITY.md).
