#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-40143428b59fe03546fabba0603268bba3b3c58b
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-40143428b59fe03546fabba0603268bba3b3c58b.tgz" -C .git
git checkout -- . 2>/dev/null || true
