#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-ab06771a67110b976953d34400d4dbc465ccd2d9
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-ab06771a67110b976953d34400d4dbc465ccd2d9.tgz" -C .git
git checkout -- . 2>/dev/null || true
