#!/bin/bash
set -e

# Extract go-git fixture: git-e1580a78f7d36791249df76df8a2a2613d629902
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-e1580a78f7d36791249df76df8a2a2613d629902.tgz' -C .git
git checkout -- . 2>/dev/null || true
