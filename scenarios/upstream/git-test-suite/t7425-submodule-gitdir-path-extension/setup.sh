#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
git init -b main legacy-sub 2>/dev/null || true
test_commit -C legacy-sub legacy-initial 2>/dev/null || true
git init -b main new-sub 2>/dev/null || true
test_commit -C new-sub new-initial 2>/dev/null || true
git init -b main main 2>/dev/null || true
(
cd main 2>/dev/null || true
git submodule add ../legacy-sub legacy 2>/dev/null || true
test_commit legacy-sub 2>/dev/null || true
git config core.repositoryformatversion 1 2>/dev/null || true
git config extensions.submodulePathConfig true 2>/dev/null || true
git submodule add ../new-sub "New Sub" 2>/dev/null || true
test_commit new 2>/dev/null || true
)
echo ".git/modules/New Sub" >expect 2>/dev/null || true
echo ".git/modules/legacy" >expect 2>/dev/null || true
git init -b main relative-cfg-path-test 2>/dev/null || true
(
cd relative-cfg-path-test 2>/dev/null || true
git config core.repositoryformatversion 1 2>/dev/null || true
git config extensions.submodulePathConfig true 2>/dev/null || true
git submodule add "$TRASH_DIRECTORY/new-sub" sub-abs 2>/dev/null || true
git config submodule.sub-abs.gitdir >actual 2>/dev/null || true
echo ".git/modules/sub-abs" >expect 2>/dev/null || true
git submodule add ../new-sub sub-rel 2>/dev/null || true
git config submodule.sub-rel.gitdir >actual 2>/dev/null || true
echo ".git/modules/sub-rel" >expect 2>/dev/null || true
)

true
