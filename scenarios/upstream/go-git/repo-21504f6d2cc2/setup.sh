#!/bin/bash
set -e

# Extract go-git fixture: git-21504f6d2cc2ef0c9d6ebb8802c7b49abae40c1a
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-21504f6d2cc2ef0c9d6ebb8802c7b49abae40c1a.tgz' -C .git
git checkout -- . 2>/dev/null || true
