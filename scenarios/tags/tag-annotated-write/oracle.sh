#!/usr/bin/env bash
set -euo pipefail

GIT_COMMITTER_NAME='Tagger Test' \
GIT_COMMITTER_EMAIL='tagger@example.com' \
GIT_COMMITTER_DATE='@1712570400 +0200' \
git tag -a v1.0 -m 'Release 1.0'

git cat-file -p refs/tags/v1.0 > .tag-state
