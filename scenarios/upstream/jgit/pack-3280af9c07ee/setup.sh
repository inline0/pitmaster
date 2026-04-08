#!/bin/bash
set -e
PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
git init .
mkdir -p .git/objects/pack
cp "${PITMASTER_ROOT}/fixtures/upstream/jgit/org.eclipse.jgit.test/tst-rsrc/org/eclipse/jgit/test/resources/pack-3280af9c07ee18a87705ef50b0cc4cd20266cf12.pack" .git/objects/pack/
cp "${PITMASTER_ROOT}/fixtures/upstream/jgit/org.eclipse.jgit.test/tst-rsrc/org/eclipse/jgit/test/resources/pack-3280af9c07ee18a87705ef50b0cc4cd20266cf12.idx" .git/objects/pack/
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true; fi
