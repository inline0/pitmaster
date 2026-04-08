#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-7a725350b88b05ca03541b59dd0649fda7f521f2
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-7a725350b88b05ca03541b59dd0649fda7f521f2.tgz" -C .git
git checkout -- . 2>/dev/null || true
