#!/bin/bash
set -e

# Extract go-git fixture: git-00a1fc100787506f842e55511994f08df2c2cd66
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-00a1fc100787506f842e55511994f08df2c2cd66.tgz' -C .git
git checkout -- . 2>/dev/null || true
