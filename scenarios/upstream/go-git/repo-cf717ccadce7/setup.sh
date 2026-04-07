#!/bin/bash
set -e

# Extract go-git fixture: git-cf717ccadce761d60bb4a8557a7b9a2efd23816a
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-cf717ccadce761d60bb4a8557a7b9a2efd23816a.tgz' -C .git
git checkout -- . 2>/dev/null || true
