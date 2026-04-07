#!/bin/bash
set -e

# Copy libgit2 fixture: describe
cp -r '/tmp/libgit2-fixtures/tests/resources/describe/.gitted' .git
# Checkout working tree from HEAD
git checkout -- . 2>/dev/null || true
