#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-77b6511a6e67c99162ebcecd2763a9a19a7ad429
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-77b6511a6e67c99162ebcecd2763a9a19a7ad429.tgz" -C .git
git checkout -- . 2>/dev/null || true
