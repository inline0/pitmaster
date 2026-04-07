#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir a aa "a[a]" 2>/dev/null || true
touch a/one aa/two "a[a]/three" 2>/dev/null || true
git add a/one aa/two "a[a]/three" 2>/dev/null || true
git commit -m test 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
