#!/bin/bash
set -e

# Copy libgit2 bare fixture: deprecated-mode.git
git init .
cp -r '/tmp/libgit2-fixtures/tests/resources/deprecated-mode.git'/* .git/
git checkout -- . 2>/dev/null || true
