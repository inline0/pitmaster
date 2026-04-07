#!/bin/bash
set -e

# go-git pack fixture: pack-7861f2632868833a35fe5e4ab94f99638ec5129b
git init .
mkdir -p .git/objects/pack
cp '/private/tmp/go-git-fixtures/data/pack-7861f2632868833a35fe5e4ab94f99638ec5129b.pack' .git/objects/pack/
cp '/private/tmp/go-git-fixtures/data/pack-7861f2632868833a35fe5e4ab94f99638ec5129b.idx' .git/objects/pack/
# Set HEAD to first commit found in pack
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then
  git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true
fi
