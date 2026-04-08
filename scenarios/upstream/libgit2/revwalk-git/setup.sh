#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
git init .
cp -r "${PITMASTER_ROOT}/fixtures/upstream/libgit2/tests/resources/revwalk.git"/* .git/
git checkout -- . 2>/dev/null || true
