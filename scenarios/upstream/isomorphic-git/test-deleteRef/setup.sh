#!/bin/bash
set -e

# isomorphic-git fixture: test-deleteRef.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-deleteRef.git'/* .git/
git checkout -- . 2>/dev/null || true
