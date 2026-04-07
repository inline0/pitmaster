#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init 2>/dev/null || true
git config core.commitGraph true 2>/dev/null || true
git config gc.writeCommitGraph false 2>/dev/null || true
test_commit $i 2>/dev/null || true
git branch commits/$i || return 1 2>/dev/null || true
git commit-graph write --reachable 2>/dev/null || true
git reset --hard commits/1 2>/dev/null || true
test_commit $i 2>/dev/null || true
git branch commits/$i || return 1 2>/dev/null || true
git reset --hard commits/2 2>/dev/null || true
test_commit $i 2>/dev/null || true
git branch commits/$i || return 1 2>/dev/null || true
git reset --hard commits/2 2>/dev/null || true
git merge commits/4 2>/dev/null || true
git branch merge/1 2>/dev/null || true
git reset --hard commits/4 2>/dev/null || true
git merge commits/6 2>/dev/null || true
git branch merge/2 2>/dev/null || true
git commit-graph write --reachable 2>/dev/null || true
(
cd fork 2>/dev/null || true
rm .git/objects/info/commit-graph 2>/dev/null || true
echo "$(pwd)/../.git/objects" >.git/objects/info/alternates 2>/dev/null || true
test_commit new-commit 2>/dev/null || true
git commit-graph write --reachable --split 2>/dev/null || true
)

true
