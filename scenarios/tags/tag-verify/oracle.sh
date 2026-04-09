#!/usr/bin/env bash
set -euo pipefail

GIT_COMMITTER_NAME='Tagger Test' \
GIT_COMMITTER_EMAIL='tagger@example.com' \
GIT_COMMITTER_DATE='@1712570400 +0200' \
git tag -a v1.0 -m 'Unsigned release'

if git verify-tag v1.0 >/dev/null 2>&1; then
    printf 'verify.failed=no\n' > .verify-state
else
    printf 'verify.failed=yes\n' > .verify-state
fi
