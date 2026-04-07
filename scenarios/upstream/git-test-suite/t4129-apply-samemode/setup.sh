#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git reset --hard 2>/dev/null || true
chmod +x file 2>/dev/null || true
git reset --hard 2>/dev/null || true
chmod +x file 2>/dev/null || true
git add file 2>/dev/null || true
git reset --hard 2>/dev/null || true
chmod +x file 2>/dev/null || true
git add file 2>/dev/null || true
git reset --hard 2>/dev/null || true
git reset --hard 2>/dev/null || true
git reset --hard 2>/dev/null || true
git reset --hard 2>/dev/null || true
git reset --hard 2>/dev/null || true
git reset --hard 2>/dev/null || true
(
mkdir d 2>/dev/null || true
touch f1 d/f2 2>/dev/null || true
git add f1 d/f2 2>/dev/null || true
rm -rf d f1 2>/dev/null || true
echo "-rw-------" >f1_mode.expected 2>/dev/null || true
echo "drwx------" >d_mode.expected 2>/dev/null || true
)
echo true >script.sh 2>/dev/null || true
git add --chmod=+x script.sh 2>/dev/null || true
test_tick && git commit -m "Add script" 2>/dev/null || true
echo true >>script.sh 2>/dev/null || true
test_tick && git commit -m "Modify script" script.sh 2>/dev/null || true
git switch -c branch HEAD^ 2>/dev/null || true
git reset --hard 2>/dev/null || true
git reset --hard 2>/dev/null || true

true
