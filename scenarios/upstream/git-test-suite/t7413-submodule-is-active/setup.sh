#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config --global protocol.file.allow always 2>/dev/null || true
git init sub 2>/dev/null || true
test_commit -C sub initial 2>/dev/null || true
git init super 2>/dev/null || true
test_commit -C super initial 2>/dev/null || true
cp super/.git/config super/.git/config.orig 2>/dev/null || true
cat >>super/.git/config <<-\EOF 2>/dev/null || true
cat >expect <<-\EOF 2>/dev/null || true

true
