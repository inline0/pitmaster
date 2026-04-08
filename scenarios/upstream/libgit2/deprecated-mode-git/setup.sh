#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Copy libgit2 bare fixture: deprecated-mode.git
git init .
cp -r "${PITMASTER_ROOT}/fixtures/upstream/libgit2/tests/resources/deprecated-mode.git"/* .git/
git checkout -- . 2>/dev/null || true
