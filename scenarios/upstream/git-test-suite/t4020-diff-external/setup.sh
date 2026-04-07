#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo initial >file
git add file 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo second >file
git add file 2>/dev/null || true
git commit -m second 2>/dev/null || true
echo third >file
echo third >file
echo third >file
git config diff.parrot.command echo 2>/dev/null || true
git config --unset diff.parrot.command 2>/dev/null || true
git config diff.color.command echo 2>/dev/null || true
echo "file diff=foo" >.gitattributes
echo \"file diff=foo\" >.gitattributes
echo output >output
echo "fatal: external diff died, stopping at file" >error
echo NULZbetweenZwords | tr "Z" "\000" > file
