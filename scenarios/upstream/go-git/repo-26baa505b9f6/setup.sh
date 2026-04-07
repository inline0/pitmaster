#!/bin/bash
set -e

# Extract go-git fixture: git-26baa505b9f6fb2024b9999c140b75514718c988
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-26baa505b9f6fb2024b9999c140b75514718c988.tgz' -C .git
git checkout -- . 2>/dev/null || true
