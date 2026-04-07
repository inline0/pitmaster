#!/bin/bash
set -e

# Extract go-git fixture: git-7cbde0ca02f13aedd5ec8b358ca17b1c0bf5ee64
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-7cbde0ca02f13aedd5ec8b358ca17b1c0bf5ee64.tgz' -C .git
git checkout -- . 2>/dev/null || true
