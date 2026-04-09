#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config --global protocol.file.allow always 2>/dev/null || true
echo file > file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m upstream 2>/dev/null || true
git submodule add ../submodule sub1 2>/dev/null || true
git submodule add ../submodule sub2 2>/dev/null || true
git submodule add ../submodule sub3 2>/dev/null || true
git config -f .gitmodules --rename-section \ 2>/dev/null || true
git config -f .gitmodules --rename-section \ 2>/dev/null || true
git config -f .gitmodules --rename-section \ 2>/dev/null || true
git add .gitmodules 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "submodules" 2>/dev/null || true
git submodule init sub1 2>/dev/null || true
git submodule init sub2 2>/dev/null || true
git submodule init sub3 2>/dev/null || true
echo different > file 2>/dev/null || true
git add file 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "different" 2>/dev/null || true
git add sub3 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "update sub3" 2>/dev/null || true
git submodule update --init -- sub1 sub3 2>/dev/null || true
git submodule foreach "echo \$toplevel-\$name-\$path-\$sha1" > ../actual 2>/dev/null || true
git config foo.bar zar 2>/dev/null || true
git submodule foreach "git config --file \"\$toplevel/.git/config\" foo.bar" 2>/dev/null || true
mkdir clone/sub 2>/dev/null || true
git submodule foreach "echo \$toplevel-\$name-\$sm_path-\$displaypath-\$sha1" >../../actual 2>/dev/null || true

true
