#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git reset --hard 2>/dev/null || true
git checkout parallel-change 2>/dev/null || true
git checkout parallel-change 2>/dev/null || true
git add c.c 2>/dev/null || true
git commit -m "many lines" 2>/dev/null || true
git add c.c 2>/dev/null || true
git commit -m "modify many lines" 2>/dev/null || true
git checkout --orphan moves-start 2>/dev/null || true
git reset --hard 2>/dev/null || true
printf "%s\n"    12 13 14 15      b c d e   >file-1 2>/dev/null || true
printf "%s\n"    22 23 24 25      B C D E   >file-2 2>/dev/null || true
git add file-1 file-2 2>/dev/null || true
test_tick 2>/dev/null || true
git commit -m "Add file-1 and file-2" 2>/dev/null || true
git checkout -b moves-main 2>/dev/null || true
printf "%s\n" 11 12 13 14 15      b c d e   >file-1 2>/dev/null || true
git commit -a -m "Modify file-1 on main" 2>/dev/null || true
printf "%s\n" 21 22 23 24 25      B C D E   >file-2 2>/dev/null || true
git commit -a -m "Modify file-2 on main #1" 2>/dev/null || true
git mv file-1 renamed-1 2>/dev/null || true
git commit -m "Rename file-1 to renamed-1 on main" 2>/dev/null || true
printf "%s\n" 11 12 13 14 15      b c d e f >renamed-1 2>/dev/null || true
git commit -a -m "Modify renamed-1 on main" 2>/dev/null || true
printf "%s\n" 21 22 23 24 25      B C D E F >file-2 2>/dev/null || true
git commit -a -m "Modify file-2 on main #2" 2>/dev/null || true
git checkout -b moves-side moves-start 2>/dev/null || true
printf "%s\n"    12 13 14 15 16   b c d e   >file-1 2>/dev/null || true
git commit -a -m "Modify file-1 on side #1" 2>/dev/null || true
printf "%s\n"    22 23 24 25 26   B C D E   >file-2 2>/dev/null || true
git commit -a -m "Modify file-2 on side" 2>/dev/null || true
git mv file-2 renamed-2 2>/dev/null || true
git commit -m "Rename file-2 to renamed-2 on side" 2>/dev/null || true
printf "%s\n"    12 13 14 15 16 a b c d e   >file-1 2>/dev/null || true
git commit -a -m "Modify file-1 on side #2" 2>/dev/null || true
printf "%s\n"    22 23 24 25 26 A B C D E   >renamed-2 2>/dev/null || true
git commit -a -m "Modify renamed-2 on side" 2>/dev/null || true
git checkout moves-main 2>/dev/null || true
git merge moves-side 2>/dev/null || true

true
