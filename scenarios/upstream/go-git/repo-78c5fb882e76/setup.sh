#!/bin/bash
set -e

# Extract go-git fixture: git-78c5fb882e76286d8201016cffee63ea7060a0c2
git init .
tar xzf '/private/tmp/go-git-fixtures/data/git-78c5fb882e76286d8201016cffee63ea7060a0c2.tgz' -C .git
git checkout -- . 2>/dev/null || true
