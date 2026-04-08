#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-c20badf43d2495f93b42cb3ea98ed04651510617da9b56d4e07c5837ec08f93d
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-c20badf43d2495f93b42cb3ea98ed04651510617da9b56d4e07c5837ec08f93d.tgz" -C .git
git checkout -- . 2>/dev/null || true
