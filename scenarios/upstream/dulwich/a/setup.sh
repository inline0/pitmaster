#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
git init .
cp -r "${PITMASTER_ROOT}/fixtures/upstream/dulwich/testdata/repos/a.git"/* .git/
git checkout -- . 2>/dev/null || true
