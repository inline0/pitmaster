#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit "1-$i" 2>/dev/null || true
git branch -f commit-1-$i 2>/dev/null || true
git tag -a -m "1-$i" tag-1-$i commit-1-$i || return 1 2>/dev/null || true
git reset --hard commit-$j-1 2>/dev/null || true
test_commit "$x-1" 2>/dev/null || true
git branch -f commit-$x-1 2>/dev/null || true
git tag -a -m "$x-1" tag-$x-1 commit-$x-1 2>/dev/null || true
git merge commit-$j-$i -m "$x-$i" 2>/dev/null || true
git branch -f commit-$x-$i 2>/dev/null || true
git tag -a -m "$x-$i" tag-$x-$i commit-$x-$i || return 1 2>/dev/null || true
git commit-graph write --reachable 2>/dev/null || true
mv .git/objects/info/commit-graph commit-graph-full 2>/dev/null || true
mv .git/objects/info/commit-graph commit-graph-half 2>/dev/null || true
mv .git/objects/info/commit-graph commit-graph-no-gdat 2>/dev/null || true
git config core.commitGraph true 2>/dev/null || true
cat >input <<-\EOF 2>/dev/null || true
echo "ref_newer(A,B):0" >expect 2>/dev/null || true
cat >input <<-\EOF 2>/dev/null || true
echo "ref_newer(A,B):1" >expect 2>/dev/null || true

true
