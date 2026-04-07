#!/bin/bash
set -e

# go-git pack fixture: pack-407497645643e18a7ba56c6132603f167fe9c51c00361ee0c81d74a8f55d0ee2
git init .
mkdir -p .git/objects/pack
cp '/private/tmp/go-git-fixtures/data/pack-407497645643e18a7ba56c6132603f167fe9c51c00361ee0c81d74a8f55d0ee2.pack' .git/objects/pack/
cp '/private/tmp/go-git-fixtures/data/pack-407497645643e18a7ba56c6132603f167fe9c51c00361ee0c81d74a8f55d0ee2.idx' .git/objects/pack/
# Set HEAD to first commit found in pack
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then
  git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true
fi
