#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init --bare remote-1 2>/dev/null || true
test_commit one 2>/dev/null || true
cat >refs <<-EOF 2>/dev/null || true
git tag -a -m "annotated" annotated-1 HEAD 2>/dev/null || true
git tag -a -m "annotated" annotated-2 HEAD 2>/dev/null || true
git update-ref --stdin < refs 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true

true
