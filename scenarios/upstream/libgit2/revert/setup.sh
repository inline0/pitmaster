#!/bin/bash
set -e

# Copy libgit2 fixture: revert
cp -r '/tmp/libgit2-fixtures/tests/resources/revert/.gitted' .git
# Checkout working tree from HEAD
git checkout -- . 2>/dev/null || true
