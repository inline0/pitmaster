#!/bin/bash
set -e

# Extract go-git fixture: git-0a00a25543e6d732dbf4e8e9fec55c8e65fc4e8d
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-0a00a25543e6d732dbf4e8e9fec55c8e65fc4e8d.tgz' -C .git
git checkout -- . 2>/dev/null || true
