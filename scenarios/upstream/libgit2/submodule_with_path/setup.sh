#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
cp -r "${PITMASTER_ROOT}/fixtures/upstream/libgit2/tests/resources/submodule_with_path/.gitted" .git
git checkout -- . 2>/dev/null || true
