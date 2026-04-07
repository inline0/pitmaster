#!/bin/bash
set -e

# isomorphic-git fixture: test-pull-ff-only.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-pull-ff-only.git'/* .git/
git checkout -- . 2>/dev/null || true
