#!/usr/bin/env bash
set -euo pipefail

branches="$(git --git-dir=. for-each-ref --format='%(refname:short)' refs/heads | sort | paste -sd'|' -)"
log_lines="$(git --git-dir=. log --oneline --abbrev=7 -n 20 | paste -sd'|' -)"
readme="$(git --git-dir=. show HEAD:README.md | tr -d '\n')"

printf 'is_bare=true\n'
printf 'branch=%s\n' "$(git --git-dir=. symbolic-ref --short HEAD)"
printf 'head=%s\n' "$(git --git-dir=. rev-parse HEAD)"
printf 'branches=%s\n' "$branches"
printf 'log=%s\n' "$log_lines"
printf 'readme=%s\n' "$readme"
