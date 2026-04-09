#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir -p a/b/ignored-dir a/submodule b 2>/dev/null || true
ln -s b a/symlink 2>/dev/null || true
git init 2>/dev/null || true
echo a >a 2>/dev/null || true
git add a 2>/dev/null || true
git commit -m"commit in submodule" 2>/dev/null || true
git add a/submodule 2>/dev/null || true
cat <<-\EOF >.gitignore 2>/dev/null || true
git add -f ignored-but-in-index a/ignored-but-in-index 2>/dev/null || true
cat <<-\EOF >a/.gitignore 2>/dev/null || true
cat <<-\EOF >a/b/.gitignore 2>/dev/null || true
echo "seven" >a/b/ignored-dir/.gitignore 2>/dev/null || true
cat <<-\EOF >"$global_excludes" 2>/dev/null || true
mkdir .git/info 2>/dev/null || true
cat <<-\EOF >.git/info/exclude 2>/dev/null || true

true
