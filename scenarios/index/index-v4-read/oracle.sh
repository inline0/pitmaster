#!/usr/bin/env bash
set -euo pipefail

fixture="$PITMASTER_ROOT/fixtures/upstream/jgit/org.eclipse.jgit.test/tst-rsrc/org/eclipse/jgit/test/resources/gitgit.index.v4"
mkdir repo
git init --initial-branch=main repo >/dev/null
cp "$fixture" repo/.git/index
git -C repo ls-files --stage > .index-state
