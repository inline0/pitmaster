#!/bin/bash
set -e

# isomorphic-git fixture: test-readTree.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-readTree.git'/* .git/
git checkout -- . 2>/dev/null || true
