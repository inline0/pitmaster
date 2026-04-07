#!/bin/bash
set -e

# Copy libgit2 bare fixture: grafted.git
git init .
cp -r '/tmp/libgit2-fixtures/tests/resources/grafted.git'/* .git/
git checkout -- . 2>/dev/null || true
