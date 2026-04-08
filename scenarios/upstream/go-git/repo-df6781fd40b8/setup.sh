#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-df6781fd40b8f4911d70ce71f8387b991615cd6d
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-df6781fd40b8f4911d70ce71f8387b991615cd6d.tgz" -C .git
git checkout -- . 2>/dev/null || true
