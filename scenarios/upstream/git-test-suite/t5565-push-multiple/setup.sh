#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m "initial" 2>/dev/null || true
git config set --append remote.them.pushurl "file://$(pwd)/dest-1" 2>/dev/null || true
git config set --append remote.them.pushurl "file://$(pwd)/dest-2" 2>/dev/null || true
git config set --append remote.them.pushurl "file://$(pwd)/dest-3" 2>/dev/null || true
git config set --append remote.them.push "+refs/heads/*:refs/heads/*" 2>/dev/null || true
