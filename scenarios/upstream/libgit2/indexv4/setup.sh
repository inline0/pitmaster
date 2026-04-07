#!/bin/bash
set -e

# Copy libgit2 fixture: indexv4
cp -r '/tmp/libgit2-fixtures/tests/resources/indexv4/.gitted' .git
# Checkout working tree from HEAD
git checkout -- . 2>/dev/null || true
