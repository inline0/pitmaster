#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit A 2>/dev/null || true
test_commit B 2>/dev/null || true
cat >tag.sig <<-EOF 2>/dev/null || true
git mktag <tag.sig 2>/dev/null || true
git mktag --end-of-options <tag.sig 2>/dev/null || true

true
