#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config trailer.bug.key "Bug-maker: " 2>/dev/null || true
git config trailer.bug.ifExists "add" 2>/dev/null || true
git config trailer.bug.cmd "echo \"maybe is\"" 2>/dev/null || true
git config trailer.bug.key "Bug-maker: " 2>/dev/null || true
git config trailer.bug.ifExists "add" 2>/dev/null || true
git config trailer.bug.cmd "echo \"\$1\" is" 2>/dev/null || true
git config trailer.bug.key "Bug-maker: " 2>/dev/null || true
git config trailer.bug.ifExists "replace" 2>/dev/null || true
git config trailer.bug.cmd "sh -c \"echo who is \"\$1\"\"" 2>/dev/null || true
git config trailer.bug.key "Bug-maker: " 2>/dev/null || true
git config trailer.bug.ifExists "replace" 2>/dev/null || true
git config trailer.bug.cmd "./echoscript" 2>/dev/null || true
echo >>expected
echo >>expected
echo >>expected
echo "Content of the first commit." > a.txt
git add a.txt 2>/dev/null || true
git commit -m "Add file a.txt" 2>/dev/null || true
echo "real-trailer: just right" >expected
echo "real-trailer: just right" >expected
echo "real-trailer: before the cut" >expected
echo "my-trailer: here" >expected
