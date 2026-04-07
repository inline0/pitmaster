#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo foo content 1 >foo.bin
echo bar content 1 >bar.bin
git add . 2>/dev/null || true
git commit -m one 2>/dev/null || true
echo foo content 2 >foo.bin
echo bar content 2 >bar.bin
git commit -a -m two 2>/dev/null || true
echo "*.bin diff=magic" >.gitattributes
git config diff.magic.textconv ./helper 2>/dev/null || true
git config diff.magic.cachetextconv true 2>/dev/null || true
echo other >other
git config diff.magic.textconv "./helper other" 2>/dev/null || true
git config diff.moremagic.textconv ./helper 2>/dev/null || true
echo foo.bin diff=moremagic >>.gitattributes
