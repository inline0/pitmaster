#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo content >"br[ack]ets"
git add . 2>/dev/null || true
git commit -m brackets 2>/dev/null || true
mkdir subdir
