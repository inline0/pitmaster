#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Extract go-git fixture: git-78c5fb882e76286d8201016cffee63ea7060a0c2
git init .
tar xzf "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/git-78c5fb882e76286d8201016cffee63ea7060a0c2.tgz" -C .git
git checkout -- . 2>/dev/null || true
