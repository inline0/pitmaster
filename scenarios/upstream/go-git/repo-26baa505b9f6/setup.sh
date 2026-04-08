#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-26baa505b9f6fb2024b9999c140b75514718c988
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-26baa505b9f6fb2024b9999c140b75514718c988.tgz" -C .git
git checkout -- . 2>/dev/null || true
