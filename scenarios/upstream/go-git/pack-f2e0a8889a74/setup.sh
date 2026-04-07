#!/bin/bash
set -e

# go-git pack fixture: pack-f2e0a8889a746f7600e07d2246a2e29a72f696be
git init .
mkdir -p .git/objects/pack
cp '/private/tmp/go-git-fixtures/data/pack-f2e0a8889a746f7600e07d2246a2e29a72f696be.pack' .git/objects/pack/
cp '/private/tmp/go-git-fixtures/data/pack-f2e0a8889a746f7600e07d2246a2e29a72f696be.idx' .git/objects/pack/
# Set HEAD to first commit found in pack
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then
  git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true
fi
