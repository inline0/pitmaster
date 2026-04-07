#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo "bin: test number 1" >one 2>/dev/null || true
git add one 2>/dev/null || true
git commit -m First --date="2010-01-01 01:00:00" 2>/dev/null || true
cat >expected_n <<-\EOF 2>/dev/null || true
cat >expected_e <<-\EOF 2>/dev/null || true

true
