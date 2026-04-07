#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --unset fetch.recurseSubmodules 2>/dev/null || true
git config --unset fetch.recurseSubmodules 2>/dev/null || true
echo a > file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m "new file" 2>/dev/null || true
git config fetch.recurseSubmodules true 2>/dev/null || true
git config --unset fetch.recurseSubmodules 2>/dev/null || true

true
