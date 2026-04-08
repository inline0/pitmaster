#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-935e5ac17c41c309c356639816ea0694a568c484
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-935e5ac17c41c309c356639816ea0694a568c484.tgz" -C .git
git checkout -- . 2>/dev/null || true
