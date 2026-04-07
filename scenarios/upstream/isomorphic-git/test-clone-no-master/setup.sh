#!/bin/bash
set -e

# isomorphic-git fixture: test-clone-no-master.git
git init .
cp -r '/private/tmp/isomorphic-git/__tests__/__fixtures__/test-clone-no-master.git'/* .git/
git checkout -- . 2>/dev/null || true
