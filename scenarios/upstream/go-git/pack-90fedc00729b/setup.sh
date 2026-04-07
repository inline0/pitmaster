#!/bin/bash
set -e

# go-git pack fixture: pack-90fedc00729b64ea0d0406db861be081cda25bbf
git init .
mkdir -p .git/objects/pack
cp '/private/tmp/go-git-fixtures/data/pack-90fedc00729b64ea0d0406db861be081cda25bbf.pack' .git/objects/pack/
cp '/private/tmp/go-git-fixtures/data/pack-90fedc00729b64ea0d0406db861be081cda25bbf.idx' .git/objects/pack/
# Set HEAD to first commit found in pack
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then
  git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true
fi
