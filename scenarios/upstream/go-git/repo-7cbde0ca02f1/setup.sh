#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-7cbde0ca02f13aedd5ec8b358ca17b1c0bf5ee64
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-7cbde0ca02f13aedd5ec8b358ca17b1c0bf5ee64.tgz" -C .git
git checkout -- . 2>/dev/null || true
