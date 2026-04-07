#!/bin/bash
set -e

# Extract go-git fixture: git-df6781fd40b8f4911d70ce71f8387b991615cd6d
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-df6781fd40b8f4911d70ce71f8387b991615cd6d.tgz' -C .git
git checkout -- . 2>/dev/null || true
