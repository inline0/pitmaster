#!/bin/bash
set -e

# Extract go-git fixture: git-40143428b59fe03546fabba0603268bba3b3c58b
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-40143428b59fe03546fabba0603268bba3b3c58b.tgz' -C .git
git checkout -- . 2>/dev/null || true
