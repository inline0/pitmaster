#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# isomorphic-git fixture: test-submodules.git
git init .
cp -r "${PITMASTER_ROOT}/fixtures/upstream/isomorphic-git/__tests__/__fixtures__/test-submodules.git"/* .git/
git checkout -- . 2>/dev/null || true
