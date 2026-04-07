#!/bin/bash
set -e

# isomorphic-git fixture: test-readTag.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-readTag.git'/* .git/
git checkout -- . 2>/dev/null || true
