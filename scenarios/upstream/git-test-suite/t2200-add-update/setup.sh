#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo initial >check
echo initial >top
echo initial >foo
mkdir dir1 dir2
echo initial >dir1/sub1
echo initial >dir1/sub2
echo initial >dir2/sub3
git add check dir1 dir2 top foo 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo changed >check
echo changed >top
echo changed >dir2/sub3
echo other >dir2/other
git add -u dir1 dir2 2>/dev/null || true
echo "M	check" >expect
echo "M	top" >expect
echo content >>baz
echo content >>top
echo more >sub2
git add -u sub2 2>/dev/null || true
echo even more >>sub2
echo even more >>sub2
git add -u 2>/dev/null || true
git add -u 2>/dev/null || true
git add -u 2>/dev/null || true
git add check 2>/dev/null || true
echo changed >>check
git add -n -u >actual 2>/dev/null || true
echo 3 >path1
echo 2 >path3
echo 2 >path5
git add -u 2>/dev/null || true
git rm -rf \* 2>/dev/null || true
git commit -m clean-slate 2>/dev/null || true
git commit -a -m remove 2>/dev/null || true
