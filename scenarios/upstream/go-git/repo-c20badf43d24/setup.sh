#!/bin/bash
set -e

# Extract go-git fixture: git-c20badf43d2495f93b42cb3ea98ed04651510617da9b56d4e07c5837ec08f93d
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-c20badf43d2495f93b42cb3ea98ed04651510617da9b56d4e07c5837ec08f93d.tgz' -C .git
git checkout -- . 2>/dev/null || true
