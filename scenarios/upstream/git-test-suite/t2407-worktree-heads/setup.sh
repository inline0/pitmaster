#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit init 2>/dev/null || true
git checkout -b conflict-$i 2>/dev/null || true
echo "not I" >$i.t 2>/dev/null || true
git add $i.t 2>/dev/null || true
git commit -m "will conflict" 2>/dev/null || true
git checkout - 2>/dev/null || true
test_commit $i 2>/dev/null || true
git branch wt-$i 2>/dev/null || true
git branch fake-$i 2>/dev/null || true
git worktree add wt-$i wt-$i || return 1 2>/dev/null || true
git init server 2>/dev/null || true
test_commit -C server initial 2>/dev/null || true
test_commit -C server A-$i || return 1 2>/dev/null || true
test_commit -C server f-$i || return 1 2>/dev/null || true

true
