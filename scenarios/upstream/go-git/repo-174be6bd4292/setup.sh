#!/bin/bash
set -e

# Extract go-git fixture: git-174be6bd4292c18160542ae6dc6704b877b8a01a
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-174be6bd4292c18160542ae6dc6704b877b8a01a.tgz' -C .git
git checkout -- . 2>/dev/null || true
