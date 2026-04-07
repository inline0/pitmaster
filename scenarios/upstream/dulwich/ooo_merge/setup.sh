#!/bin/bash
set -e
git init .
cp -r '/private/tmp/dulwich/testdata/repos/ooo_merge.git'/* .git/
git checkout -- . 2>/dev/null || true
