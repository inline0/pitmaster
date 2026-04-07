#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init ${enlistment_root}/src 2>/dev/null || true
git init ${enlistment_root}/src 2>/dev/null || true
git init ${enlistment_root} 2>/dev/null || true
git init test/src 2>/dev/null || true
mkdir -p test/src/deep 2>/dev/null || true
git init --bare bare/src 2>/dev/null || true
git init test/src 2>/dev/null || true
git init test/src 2>/dev/null || true
git init register-repo 2>/dev/null || true
git init vanish/src 2>/dev/null || true
git config --get --global --fixed-value \ 2>/dev/null || true
rm -rf vanish/src/.git 2>/dev/null || true
git init register-no-maint 2>/dev/null || true
test_commit first 2>/dev/null || true
test_commit second 2>/dev/null || true
test_commit third 2>/dev/null || true
git switch -c parallel first 2>/dev/null || true
mkdir -p 1/2 2>/dev/null || true
test_commit 1/2/3 2>/dev/null || true
git config uploadPack.allowFilter true 2>/dev/null || true
git config uploadPack.allowAnySHA1InWant true 2>/dev/null || true
(
cd cloned/src 2>/dev/null || true
git config --get --global --fixed-value maintenance.repo \ 2>/dev/null || true
echo "refs/remotes/origin/HEAD" >>expect 2>/dev/null || true
echo "refs/remotes/origin/parallel" >>expect 2>/dev/null || true
echo "second" >expect 2>/dev/null || true
)
(
cd no-opts 2>/dev/null || true
)
git init one/src 2>/dev/null || true
rm one/src/cron.txt 2>/dev/null || true

true
