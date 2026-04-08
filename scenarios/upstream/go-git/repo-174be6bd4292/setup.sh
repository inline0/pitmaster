#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-174be6bd4292c18160542ae6dc6704b877b8a01a
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-174be6bd4292c18160542ae6dc6704b877b8a01a.tgz" -C .git
git checkout -- . 2>/dev/null || true
