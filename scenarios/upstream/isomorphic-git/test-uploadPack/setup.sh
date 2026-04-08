#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# isomorphic-git fixture: test-uploadPack.git
git init .
cp -r "${PITMASTER_ROOT}/fixtures/upstream/isomorphic-git/__tests__/__fixtures__/test-uploadPack.git"/* .git/
git checkout -- . 2>/dev/null || true
