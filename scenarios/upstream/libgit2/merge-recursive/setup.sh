#!/bin/bash
set -e

# Copy libgit2 fixture: merge-recursive
cp -r '/tmp/libgit2-fixtures/tests/resources/merge-recursive/.gitted' .git
# Checkout working tree from HEAD
git checkout -- . 2>/dev/null || true
