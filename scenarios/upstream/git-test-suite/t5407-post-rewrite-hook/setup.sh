#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit A foo A 2>/dev/null || true
test_commit B foo B 2>/dev/null || true
test_commit C foo C 2>/dev/null || true
test_commit D foo D 2>/dev/null || true
git checkout A^0 2>/dev/null || true
test_commit E bar E 2>/dev/null || true
test_commit F foo F 2>/dev/null || true
git checkout B 2>/dev/null || true
git merge E 2>/dev/null || true
git tag merge-E 2>/dev/null || true
test_commit G G 2>/dev/null || true
test_commit H H 2>/dev/null || true
test_commit I I 2>/dev/null || true
git checkout main 2>/dev/null || true
echo \$@ > "$TRASH_DIRECTORY"/post-rewrite.args 2>/dev/null || true
cat > "$TRASH_DIRECTORY"/post-rewrite.data 2>/dev/null || true
echo "D new message" > newmsg 2>/dev/null || true
git commit -Fnewmsg --amend 2>/dev/null || true
echo amend > expected.args 2>/dev/null || true
echo $oldsha $(git rev-parse HEAD^0) > expected.data 2>/dev/null || true
echo "D new message again" > newmsg 2>/dev/null || true
git commit --no-post-rewrite -Fnewmsg --amend 2>/dev/null || true

true
