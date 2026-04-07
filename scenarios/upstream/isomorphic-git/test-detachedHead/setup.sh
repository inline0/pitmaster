#!/bin/bash
set -e

# isomorphic-git fixture: test-detachedHead.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-detachedHead.git'/* .git/
git checkout -- . 2>/dev/null || true
