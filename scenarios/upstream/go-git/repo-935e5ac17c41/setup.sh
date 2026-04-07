#!/bin/bash
set -e

# Extract go-git fixture: git-935e5ac17c41c309c356639816ea0694a568c484
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-935e5ac17c41c309c356639816ea0694a568c484.tgz' -C .git
git checkout -- . 2>/dev/null || true
