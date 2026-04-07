#!/bin/bash
set -e

# Copy libgit2 bare fixture: duplicate.git
git init .
cp -r '/tmp/libgit2-fixtures/tests/resources/duplicate.git'/* .git/
git checkout -- . 2>/dev/null || true
