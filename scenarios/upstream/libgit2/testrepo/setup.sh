#!/bin/bash
set -e

# Copy libgit2 fixture: testrepo
cp -r '/tmp/libgit2-fixtures/tests/resources/testrepo/.gitted' .git
# Checkout working tree from HEAD
git checkout -- . 2>/dev/null || true
