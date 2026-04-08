#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-21504f6d2cc2ef0c9d6ebb8802c7b49abae40c1a
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-21504f6d2cc2ef0c9d6ebb8802c7b49abae40c1a.tgz" -C .git
git checkout -- . 2>/dev/null || true
