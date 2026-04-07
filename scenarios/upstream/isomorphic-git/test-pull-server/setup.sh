#!/bin/bash
set -e

# isomorphic-git fixture: test-pull-server.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-pull-server.git'/* .git/
git checkout -- . 2>/dev/null || true
