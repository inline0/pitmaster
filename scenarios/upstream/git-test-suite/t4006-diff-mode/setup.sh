#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo frotz >rezrov 2>/dev/null || true
git update-index --add rezrov 2>/dev/null || true
echo ":100644 100755 X X M	rezrov" >expected 2>/dev/null || true
git commit -m one 2>/dev/null || true
test_commit --printf two binbin "\00\01\02\03\04\05\06" 2>/dev/null || true

true
