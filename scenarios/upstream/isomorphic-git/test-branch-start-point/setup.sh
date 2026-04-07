#!/bin/bash
set -e

# isomorphic-git fixture: test-branch-start-point.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-branch-start-point.git'/* .git/
git checkout -- . 2>/dev/null || true
