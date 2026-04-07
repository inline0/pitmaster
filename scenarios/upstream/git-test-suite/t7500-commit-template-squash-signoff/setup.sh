#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --fixup=amend:HEAD~ 2>/dev/null || true
git commit --fixup=amend:HEAD~ --only 2>/dev/null || true
git commit --fixup=reword:HEAD~ 2>/dev/null || true
echo "fatal: options '\''-m'\'' and '\''--fixup:reword'\'' cannot be used together" >expect
echo "fatal: options '\''-m'\'' and '\''--fixup:amend'\'' cannot be used together" >expect
echo "reword new commit message" >actual
git commit --fixup=reword:HEAD~ 2>/dev/null || true
git commit --fixup=reword:HEAD 2>/dev/null || true
echo "Aborting commit due to empty commit message body." >expected
git commit --fixup=amend:HEAD~ 2>actual 2>/dev/null || true
git commit --fixup=amend:HEAD~ --allow-empty-message 2>/dev/null || true
echo "fatal: reword option of '\''--fixup'\'' and path '\''foo'\'' cannot be used together" >expect
echo "fatal: options '\''-F'\'' and '\''--fixup'\'' cannot be used together" >expect
echo "log message from file" >msgfile
git commit --squash HEAD~1 -F msgfile 2>/dev/null || true
git commit --squash HEAD~1 -m "foo bar\nbaz" 2>/dev/null || true
git commit --squash HEAD~1 -C HEAD 2>/dev/null || true
git commit --squash HEAD~1 -c HEAD 2>/dev/null || true
git commit --squash HEAD -C HEAD 2>/dev/null || true
git commit --squash HEAD -c HEAD 2>/dev/null || true
git commit --squash HEAD~1 2>/dev/null || true
echo changes >>foo
echo "message" >log
git add foo 2>/dev/null || true
git checkout -b commit-template-check 2>/dev/null || true
git add commit-template-check 2>/dev/null || true
echo content >orig
git add orig 2>/dev/null || true
git add -N new_copy new_rename 2>/dev/null || true
echo "initial" >file
git add file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo "changes" >>file
