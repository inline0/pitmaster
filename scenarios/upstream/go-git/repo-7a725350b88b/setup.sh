#!/bin/bash
set -e

# Extract go-git fixture: git-7a725350b88b05ca03541b59dd0649fda7f521f2
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-7a725350b88b05ca03541b59dd0649fda7f521f2.tgz' -C .git
git checkout -- . 2>/dev/null || true
