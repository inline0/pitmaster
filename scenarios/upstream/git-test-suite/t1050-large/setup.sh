#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config --global core.bigfilethreshold 200k 2>/dev/null || true
printf "%2000000s" X >large1
printf "%2500000s" Y >huge
git add large1 huge large2 2>/dev/null || true
git add large3 2>/dev/null || true
git checkout another 2>/dev/null || true
git config core.bigfilethreshold 64k 2>/dev/null || true
git config pack.packsizelimit 256k 2>/dev/null || true
git add mid1 mid2 mid3 2>/dev/null || true
git commit -q -m initial 2>/dev/null || true
echo modified >>large1
git add large1 2>/dev/null || true
git commit -q -m modified 2>/dev/null || true
git tag -m largefile largefiletag :large1 2>/dev/null || true
