#!/bin/bash
set -e

# go-git pack fixture: pack-29f304662fd64f102d94722cf5bd8802d9a9472c
git init .
mkdir -p .git/objects/pack
cp '/private/tmp/go-git-fixtures/data/pack-29f304662fd64f102d94722cf5bd8802d9a9472c.pack' .git/objects/pack/
cp '/private/tmp/go-git-fixtures/data/pack-29f304662fd64f102d94722cf5bd8802d9a9472c.idx' .git/objects/pack/
# Set HEAD to first commit found in pack
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then
  git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true
fi
