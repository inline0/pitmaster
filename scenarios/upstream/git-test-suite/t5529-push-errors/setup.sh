#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit one 2>/dev/null || true
git init --bare remote.git 2>/dev/null || true
echo yes >"$FAKE_RP_ROOT"/rp-ran 2>/dev/null || true
git config remote.origin.receivepack "\"\$FAKE_RP_ROOT/fake-rp\"" 2>/dev/null || true

true
