#!/bin/bash
set -e

# Copy libgit2 fixture: merge-resolve
cp -r '/tmp/libgit2-fixtures/tests/resources/merge-resolve/.gitted' .git
# Checkout working tree from HEAD
git checkout -- . 2>/dev/null || true
