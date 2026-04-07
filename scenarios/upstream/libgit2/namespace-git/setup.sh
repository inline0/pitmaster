#!/bin/bash
set -e
git init .
cp -r '/private/tmp/libgit2-fixtures/tests/resources/namespace.git'/* .git/
git checkout -- . 2>/dev/null || true
