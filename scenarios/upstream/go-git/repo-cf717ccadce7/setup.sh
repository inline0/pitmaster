#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-cf717ccadce761d60bb4a8557a7b9a2efd23816a
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-cf717ccadce761d60bb4a8557a7b9a2efd23816a.tgz" -C .git
git checkout -- . 2>/dev/null || true
