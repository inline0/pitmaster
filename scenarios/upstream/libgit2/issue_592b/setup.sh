#!/bin/bash
set -e
cp -r '/private/tmp/libgit2-fixtures/tests/resources/issue_592b/.gitted' .git
git checkout -- . 2>/dev/null || true
