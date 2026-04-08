#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-0a00a25543e6d732dbf4e8e9fec55c8e65fc4e8d
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-0a00a25543e6d732dbf4e8e9fec55c8e65fc4e8d.tgz" -C .git
git checkout -- . 2>/dev/null || true
