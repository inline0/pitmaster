#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
git init --bare remote 2>/dev/null || true
test_create_repo bundle1 2>/dev/null || true
test_commit "shoot" 2>/dev/null || true
mkdir home 2>/dev/null || true
git submodule add ../bundle1 .vim/bundle/sogood 2>/dev/null || true
test_commit "sogood" 2>/dev/null || true
mkdir home2 2>/dev/null || true
git checkout main 2>/dev/null || true
git submodule update --init 2>/dev/null || true
mkdir home3 2>/dev/null || true
git config core.bare false 2>/dev/null || true
git config core.worktree .. 2>/dev/null || true
git checkout main 2>/dev/null || true
git submodule add ../bundle1 .vim/bundle/dupe 2>/dev/null || true
test_commit "dupe" 2>/dev/null || true
git config core.bare false 2>/dev/null || true
git config core.worktree .. 2>/dev/null || true
git submodule update --init 2>/dev/null || true

true
