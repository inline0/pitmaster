#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo ignored >ignored 2>/dev/null || true
mkdir .git/info 2>/dev/null || true
echo ignored export-ignore >>.git/info/attributes 2>/dev/null || true
git add ignored 2>/dev/null || true
echo ignored by tree >ignored-by-tree 2>/dev/null || true
echo ignored-by-tree export-ignore >.gitattributes 2>/dev/null || true
mkdir ignored-by-tree.d 2>/dev/null || true
echo ignored-by-tree.d export-ignore >>.gitattributes 2>/dev/null || true
git add ignored-by-tree ignored-by-tree.d .gitattributes 2>/dev/null || true
mkdir subdir 2>/dev/null || true
echo ignored-by-subtree export-ignore >subdir/.gitattributes 2>/dev/null || true
git add subdir 2>/dev/null || true
echo ignored by worktree >ignored-by-worktree 2>/dev/null || true
echo ignored-by-worktree export-ignore >.gitattributes 2>/dev/null || true
git add ignored-by-worktree 2>/dev/null || true
mkdir excluded-by-pathspec.d 2>/dev/null || true
git add excluded-by-pathspec.d 2>/dev/null || true
printf "A\$Format:%s\$O" "$SUBSTFORMAT" >nosubstfile 2>/dev/null || true
printf "A\$Format:%s\$O" "$SUBSTFORMAT" >substfile1 2>/dev/null || true
printf "A not substituted O" >substfile2 2>/dev/null || true
echo "substfile?" export-subst >>.git/info/attributes 2>/dev/null || true
git add nosubstfile substfile1 substfile2 2>/dev/null || true
git commit -m. 2>/dev/null || true
mkdir bare/info 2>/dev/null || true
cp .git/info/attributes bare/info/attributes 2>/dev/null || true

true
