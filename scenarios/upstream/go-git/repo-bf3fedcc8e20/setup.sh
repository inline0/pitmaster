#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-bf3fedcc8e20fd0dec9172987ceea0038d17b516
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-bf3fedcc8e20fd0dec9172987ceea0038d17b516.tgz" -C .git
git checkout -- . 2>/dev/null || true
