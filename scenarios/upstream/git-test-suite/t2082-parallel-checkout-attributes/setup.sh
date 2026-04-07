#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
echo "A ident" >.gitattributes
echo "\$Id\$" >A
echo "\$Id\$" >B
git add -A  2>/dev/null || true
git commit -m id  2>/dev/null || true
echo text >utf8-text
