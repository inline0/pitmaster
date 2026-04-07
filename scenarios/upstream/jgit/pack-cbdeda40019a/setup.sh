#!/bin/bash
set -e
git init .
mkdir -p .git/objects/pack
cp '/private/tmp/jgit/org.eclipse.jgit.test/tst-rsrc/org/eclipse/jgit/test/resources/pack-cbdeda40019ae0e6e789088ea0f51f164f489d14.pack' .git/objects/pack/
cp '/private/tmp/jgit/org.eclipse.jgit.test/tst-rsrc/org/eclipse/jgit/test/resources/pack-cbdeda40019ae0e6e789088ea0f51f164f489d14.idx' .git/objects/pack/
FIRST_COMMIT=$(git cat-file --batch-all-objects --batch-check='%(objectname) %(objecttype)' 2>/dev/null | grep ' commit' | head -1 | cut -d' ' -f1)
if [ -n "$FIRST_COMMIT" ]; then git update-ref refs/heads/main $FIRST_COMMIT 2>/dev/null || true; fi
