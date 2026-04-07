#!/bin/bash
set -e

# isomorphic-git fixture: test-statusMatrix-blob-tree-collision.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-statusMatrix-blob-tree-collision.git'/* .git/
git checkout -- . 2>/dev/null || true
