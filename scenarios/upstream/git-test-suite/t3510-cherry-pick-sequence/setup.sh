#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config set advice.detachedhead false 2>/dev/null || true
echo unrelated >unrelated
git add unrelated 2>/dev/null || true
git checkout HEAD foo 2>/dev/null || true
echo "true" >expect
git config --file=.git/sequencer/opts --get-all options.signoff >actual 2>/dev/null || true
echo "$mainline" >expect
git config --file=.git/sequencer/opts --get-all options.mainline >actual 2>/dev/null || true
echo "recursive" >expect
git config --file=.git/sequencer/opts --get-all options.strategy >actual 2>/dev/null || true
git config --file=.git/sequencer/opts --get-all options.strategy-option >actual 2>/dev/null || true
echo "true" >expect
git config --file=.git/sequencer/opts --get-all options.edit >actual 2>/dev/null || true
echo "true" >expect
git config --file=.git/sequencer/opts --get-all options.signoff >actual 2>/dev/null || true
echo "recursive" >expect
git config --file=.git/sequencer/opts --get-all options.strategy >actual 2>/dev/null || true
git config --file=.git/sequencer/opts --get-all options.strategy-option >actual 2>/dev/null || true
echo "false" >expect
git config --file=.git/sequencer/opts --get-all options.edit >actual 2>/dev/null || true
echo e >expect
echo d >foo
git add foo 2>/dev/null || true
echo c >foo
git commit -a 2>/dev/null || true
git checkout --orphan new_disconnected 2>/dev/null || true
git rm --cached unrelated 2>/dev/null || true
git commit -m "untrack unrelated" 2>/dev/null || true
echo changed >expect
echo changed >unrelated
echo changed >expect
echo changed >unrelated
git checkout unrelated 2>/dev/null || true
echo "resolved" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
echo c >expect
echo c >foo
git add foo 2>/dev/null || true
echo resolved >expect
echo "Revert \"picked\"" >expect.msg
echo resolved >foo
git add foo 2>/dev/null || true
echo d >expect
echo c >foo
git add foo 2>/dev/null || true
echo "c" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
git checkout HEAD -- unrelated 2>/dev/null || true
git checkout HEAD -- unrelated 2>/dev/null || true
echo "c" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
echo c >foo
git add foo 2>/dev/null || true
echo c >foo
git add foo 2>/dev/null || true
echo "c" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
echo c >foo
git add foo 2>/dev/null || true
echo c >foo
git add foo 2>/dev/null || true
echo "resolved" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
echo "resolved" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
echo "resolved" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
echo "c" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
echo "c" >foo
git add foo 2>/dev/null || true
git commit 2>/dev/null || true
