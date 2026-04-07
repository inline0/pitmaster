#!/bin/bash
set -e

# isomorphic-git fixture: test-fetch-cors.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-fetch-cors.git'/* .git/
git checkout -- . 2>/dev/null || true
