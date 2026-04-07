#!/bin/bash
set -e

# Extract go-git fixture: git-ab06771a67110b976953d34400d4dbc465ccd2d9
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-ab06771a67110b976953d34400d4dbc465ccd2d9.tgz' -C .git
git checkout -- . 2>/dev/null || true
