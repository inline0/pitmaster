#!/bin/bash
set -e

# Extract go-git fixture: git-77b6511a6e67c99162ebcecd2763a9a19a7ad429
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-77b6511a6e67c99162ebcecd2763a9a19a7ad429.tgz' -C .git
git checkout -- . 2>/dev/null || true
