#!/bin/bash
set -e

# Copy libgit2 bare fixture: blametest.git
git init .
cp -r '/tmp/libgit2-fixtures/tests/resources/blametest.git'/* .git/
git checkout -- . 2>/dev/null || true
