#!/bin/bash
set -e

# Extract go-git fixture: git-c0c7c57ab1753ddbd26cc45322299ddd12842794
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-c0c7c57ab1753ddbd26cc45322299ddd12842794.tgz' -C .git
git checkout -- . 2>/dev/null || true
