#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config --global protocol.file.allow always 2>/dev/null || true
git config submodule.active "lib/*" 2>/dev/null || true
git commit --allow-empty -m init 2>/dev/null || true
