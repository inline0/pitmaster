#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-c0c7c57ab1753ddbd26cc45322299ddd12842794
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-c0c7c57ab1753ddbd26cc45322299ddd12842794.tgz" -C .git
git checkout -- . 2>/dev/null || true
