#!/bin/bash
set -e

# isomorphic-git fixture: test-status.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-status.git'/* .git/
git checkout -- . 2>/dev/null || true
