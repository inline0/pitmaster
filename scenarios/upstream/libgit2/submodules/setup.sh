#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# Copy libgit2 fixture: submodules
cp -r "${PITMASTER_ROOT}/fixtures/upstream/libgit2/tests/resources/submodules/.gitted" .git
# Checkout working tree from HEAD
git checkout -- . 2>/dev/null || true
