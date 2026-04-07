#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo "file for commit #$i" > file
git add file 2>/dev/null || true
git commit -q -m "commit #$i" 2>/dev/null || true
git notes add -m "note #$i" || return 1 2>/dev/null || true
echo "Fanout 0 -> 1 at refs/notes/commits~$i"
git notes remove "$sha1" || return 1 2>/dev/null || true
echo "Fanout 1 -> 0 at refs/notes/commits~$i"
