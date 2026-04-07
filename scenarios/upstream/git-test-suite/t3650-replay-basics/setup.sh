#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit A 2>/dev/null || true
test_commit B 2>/dev/null || true
git switch -c topic1 2>/dev/null || true
test_commit C 2>/dev/null || true
git switch -c topic2 2>/dev/null || true
test_commit D 2>/dev/null || true
test_commit E 2>/dev/null || true
git switch topic1 2>/dev/null || true
test_commit F 2>/dev/null || true
git switch -c topic3 2>/dev/null || true
test_commit G 2>/dev/null || true
test_commit H 2>/dev/null || true
git switch -c empty 2>/dev/null || true
git commit --allow-empty -m empty 2>/dev/null || true
git switch -c topic4 main 2>/dev/null || true
test_commit I 2>/dev/null || true
test_commit J 2>/dev/null || true
git switch -c next main 2>/dev/null || true
test_commit K 2>/dev/null || true
git merge -m "Merge topic1" topic1 2>/dev/null || true
git merge -m "Merge topic2" topic2 2>/dev/null || true
git merge -m "Merge topic3" topic3 2>/dev/null || true
git add evil 2>/dev/null || true
git commit --amend 2>/dev/null || true
git merge -m "Merge topic4" topic4 2>/dev/null || true
git switch main 2>/dev/null || true
test_commit L 2>/dev/null || true
test_commit M 2>/dev/null || true
git switch --detach topic4 2>/dev/null || true
test_commit N 2>/dev/null || true
test_commit O 2>/dev/null || true
git switch -c topic-with-merge topic4 2>/dev/null || true
test_merge P O --no-ff 2>/dev/null || true
git switch main 2>/dev/null || true
git switch -c conflict B 2>/dev/null || true
test_commit C.conflict C.t conflict 2>/dev/null || true
echo "fatal: argument to --advance must be a reference" >expect 2>/dev/null || true

true
