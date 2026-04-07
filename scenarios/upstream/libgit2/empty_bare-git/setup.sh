#!/bin/bash
set -e

# Copy libgit2 bare fixture: empty_bare.git
git init .
cp -r '/tmp/libgit2-fixtures/tests/resources/empty_bare.git'/* .git/
git checkout -- . 2>/dev/null || true
