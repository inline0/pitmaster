#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir -p .git/custom-hooks 2>/dev/null || true
echo CUSTOM >>actual 2>/dev/null || true
echo NORMAL >>actual 2>/dev/null || true
test_commit no_custom_hook 2>/dev/null || true
git config core.hooksPath .git/custom-hooks 2>/dev/null || true
test_commit have_custom_hook 2>/dev/null || true
git config core.hooksPath .git/custom-hooks/ 2>/dev/null || true
test_commit have_custom_hook_trailing_slash 2>/dev/null || true
git config core.hooksPath "$PWD/.git/custom-hooks" 2>/dev/null || true
test_commit have_custom_hook_abs_path 2>/dev/null || true
git config core.hooksPath "$PWD/.git/custom-hooks/" 2>/dev/null || true
test_commit have_custom_hook_abs_path_trailing_slash 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true
git config core.hooksPath .git/custom-hooks 2>/dev/null || true

true
