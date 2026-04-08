#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"

# go-git pack fixture: pack-4ec6344877f494690fc800aceaf2ca0e86786acb
git init .
mkdir -p .git/objects/pack
cp "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/pack-4ec6344877f494690fc800aceaf2ca0e86786acb.pack" .git/objects/pack/
cp "${PITMASTER_ROOT}/fixtures/upstream/go-git/data/pack-4ec6344877f494690fc800aceaf2ca0e86786acb.idx" .git/objects/pack/
# Set HEAD to first commit found in pack
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then
  git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true
fi
