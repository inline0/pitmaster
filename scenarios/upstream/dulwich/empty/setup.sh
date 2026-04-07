#!/bin/bash
set -e
git init .
cp -r '/private/tmp/dulwich/testdata/repos/empty.git'/* .git/
git checkout -- . 2>/dev/null || true
