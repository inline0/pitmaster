#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
git init upstream 2>/dev/null || true
git init submodule 2>/dev/null || true
echo file >file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Add file" 2>/dev/null || true
git submodule add ../submodule 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Add submodule" 2>/dev/null || true
mkdir .git/info 2>/dev/null || true
cat >.git/info/sparse-checkout <<-\EOF 2>/dev/null || true
git config core.sparsecheckout true 2>/dev/null || true
git read-tree -m -u HEAD 2>/dev/null || true
echo "../submodule" >expect 2>/dev/null || true

true
