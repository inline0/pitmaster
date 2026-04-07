#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git reset --hard main 2>/dev/null || true
git reset --hard main 2>/dev/null || true
git merge -s recursive -Xtheirs side 2>/dev/null || true
git reset --hard main 2>/dev/null || true
git merge -s recursive -X ours side 2>/dev/null || true
echo file binary >.gitattributes 2>/dev/null || true
git reset --hard main 2>/dev/null || true
git merge -s recursive -X theirs side 2>/dev/null || true
git reset --hard main 2>/dev/null || true
git merge -s recursive -X ours side 2>/dev/null || true
git reset --hard main && git pull --no-rebase -s recursive -Xours . side 2>/dev/null || true
git reset --hard main && git pull --no-rebase -s recursive -X ours . side 2>/dev/null || true
git reset --hard main && git pull --no-rebase -s recursive -Xtheirs . side 2>/dev/null || true
git reset --hard main && git pull --no-rebase -s recursive -X theirs . side 2>/dev/null || true
git reset --hard main && test_must_fail git pull --no-rebase -s recursive -X bork . side 2>/dev/null || true
git reset --hard main 2>/dev/null || true
git checkout -b two main 2>/dev/null || true
ln -s target-zero link 2>/dev/null || true
git add link 2>/dev/null || true
git commit -m "add link pointing to zero" 2>/dev/null || true
ln -f -s target-two link 2>/dev/null || true
git commit -m "add link pointing to two" link 2>/dev/null || true
git checkout -b one HEAD^ 2>/dev/null || true
ln -f -s target-one link 2>/dev/null || true
git commit -m "add link pointing to one" link 2>/dev/null || true
git checkout one^0 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout one^0 2>/dev/null || true
git merge -s recursive -X theirs two 2>/dev/null || true
git reset --hard 2>/dev/null || true
git checkout one^0 2>/dev/null || true
git merge -s recursive -X ours two 2>/dev/null || true

true
