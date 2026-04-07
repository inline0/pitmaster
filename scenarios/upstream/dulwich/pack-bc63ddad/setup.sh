#!/bin/bash
set -e
git init .
mkdir -p .git/objects/pack
cp '/private/tmp/dulwich/testdata/packs/pack-bc63ddad95e7321ee734ea11a7a62d314e0d7481.pack' .git/objects/pack/
cp '/private/tmp/dulwich/testdata/packs/pack-bc63ddad95e7321ee734ea11a7a62d314e0d7481.idx' .git/objects/pack/
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true; fi
