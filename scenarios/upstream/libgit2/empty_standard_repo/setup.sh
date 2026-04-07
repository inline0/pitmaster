#!/bin/bash
set -e

# Copy libgit2 fixture: empty_standard_repo
cp -r '/tmp/libgit2-fixtures/tests/resources/empty_standard_repo/.gitted' .git
# Checkout working tree from HEAD
git checkout -- . 2>/dev/null || true
