#!/bin/bash
set -e

# isomorphic-git fixture: test-resetIndex-oid.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-resetIndex-oid.git'/* .git/
git checkout -- . 2>/dev/null || true
