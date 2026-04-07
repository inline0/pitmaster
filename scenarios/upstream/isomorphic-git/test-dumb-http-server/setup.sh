#!/bin/bash
set -e

# isomorphic-git fixture: test-dumb-http-server.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-dumb-http-server.git'/* .git/
git checkout -- . 2>/dev/null || true
