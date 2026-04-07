#!/bin/bash
set -e

# isomorphic-git fixture: test-statusMatrix-tree-blob-collision.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-statusMatrix-tree-blob-collision.git'/* .git/
git checkout -- . 2>/dev/null || true
