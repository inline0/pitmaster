#!/bin/bash
set -e

# isomorphic-git fixture: test-push-server-auth.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-push-server-auth.git'/* .git/
git checkout -- . 2>/dev/null || true
