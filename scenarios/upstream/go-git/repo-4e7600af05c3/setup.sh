#!/bin/bash
set -e

# Extract go-git fixture: git-4e7600af05c3356e8b142263e127b76f010facfc
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-4e7600af05c3356e8b142263e127b76f010facfc.tgz' -C .git
git checkout -- . 2>/dev/null || true
