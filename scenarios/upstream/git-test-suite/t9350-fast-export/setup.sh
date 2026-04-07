#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo break it > file0 2>/dev/null || true
git add file0 2>/dev/null || true
test_tick 2>/dev/null || true
echo Wohlauf > file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo die Luft > file 2>/dev/null || true
echo geht frisch > file2 2>/dev/null || true
git add file file2 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m second 2>/dev/null || true
echo und > file2 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m third file2 2>/dev/null || true
test_tick 2>/dev/null || true
git tag rein 2>/dev/null || true
git checkout -b wer HEAD^ 2>/dev/null || true
echo lange > file2 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m sitzt file2 2>/dev/null || true
test_tick 2>/dev/null || true
git tag -a -m valentin muss 2>/dev/null || true
git merge -s ours main 2>/dev/null || true
MAIN=$(git rev-parse --verify main) 2>/dev/null || true
REIN=$(git rev-parse --verify rein) 2>/dev/null || true
WER=$(git rev-parse --verify wer) 2>/dev/null || true
MUSS=$(git rev-parse --verify muss) 2>/dev/null || true
mkdir new 2>/dev/null || true
cat >expected <<-EOF 2>/dev/null || true

true
