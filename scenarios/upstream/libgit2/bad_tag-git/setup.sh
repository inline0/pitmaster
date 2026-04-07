#!/bin/bash
set -e

# Copy libgit2 bare fixture: bad_tag.git
git init .
cp -r '/tmp/libgit2-fixtures/tests/resources/bad_tag.git'/* .git/
git checkout -- . 2>/dev/null || true
