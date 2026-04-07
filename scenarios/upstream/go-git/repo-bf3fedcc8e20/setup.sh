#!/bin/bash
set -e

# Extract go-git fixture: git-bf3fedcc8e20fd0dec9172987ceea0038d17b516
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-bf3fedcc8e20fd0dec9172987ceea0038d17b516.tgz' -C .git
git checkout -- . 2>/dev/null || true
