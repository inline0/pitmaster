#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo $i >$i.txt 2>/dev/null || true
git add $i.txt || return 1 2>/dev/null || true
git commit -m "init" 2>/dev/null || true
git checkout -b side 2>/dev/null || true
echo $i/2 >>$i.txt || return 1 2>/dev/null || true
git commit -a -m "side 2-9" 2>/dev/null || true
git checkout main 2>/dev/null || true
echo 1/2 >1.txt 2>/dev/null || true
git commit -a -m "main 1" 2>/dev/null || true
git merge side 2>/dev/null || true
git checkout side 2>/dev/null || true
echo $i/3 >>$i.txt || return 1 2>/dev/null || true
echo "4side" >>4.txt 2>/dev/null || true
git commit -a -m "side 2-9 +4" 2>/dev/null || true
git checkout main 2>/dev/null || true
echo $i/3 >>$i.txt || return 1 2>/dev/null || true
echo "4main" >>4.txt 2>/dev/null || true
git commit -a -m "main 1-9 +4" 2>/dev/null || true
cat <<-\EOF >4.txt 2>/dev/null || true
git add 4.txt 2>/dev/null || true
git commit -m "merge side (2)" 2>/dev/null || true
echo 4.txt >diffc.expect 2>/dev/null || true
git checkout side 2>/dev/null || true
echo $i/4 >>$i.txt || return 1 2>/dev/null || true
git commit -a -m "side 5-9" 2>/dev/null || true
git checkout main 2>/dev/null || true
echo $i/4 >>$i.txt || return 1 2>/dev/null || true
git commit -a -m "main 1-3 +4hello" 2>/dev/null || true
git merge side 2>/dev/null || true
echo "Hello World" >4hello.txt 2>/dev/null || true
git add 4hello.txt 2>/dev/null || true
git commit --amend 2>/dev/null || true
echo 4hello.txt >diffc.expect 2>/dev/null || true

true
