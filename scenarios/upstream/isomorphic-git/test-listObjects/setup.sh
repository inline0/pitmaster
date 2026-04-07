#!/bin/bash
set -e

# isomorphic-git fixture: test-listObjects.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-listObjects.git'/* .git/
git checkout -- . 2>/dev/null || true
