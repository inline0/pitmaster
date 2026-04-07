#!/bin/bash
set -e
cp -r '/private/tmp/libgit2-fixtures/tests/resources/diff_format_email/.gitted' .git
git checkout -- . 2>/dev/null || true
