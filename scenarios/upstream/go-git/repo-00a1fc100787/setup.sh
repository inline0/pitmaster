#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-00a1fc100787506f842e55511994f08df2c2cd66
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-00a1fc100787506f842e55511994f08df2c2cd66.tgz" -C .git
git checkout -- . 2>/dev/null || true
