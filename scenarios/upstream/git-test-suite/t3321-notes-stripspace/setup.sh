#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit 1st 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
git notes show >actual 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
git notes add -m "${LF}first-line${MULTI_LF}second-line${LF}" 2>/dev/null || true
git notes show >actual 2>/dev/null || true
git notes remove 2>/dev/null || true
git notes add --stripspace -m "${LF}first-line${MULTI_LF}second-line${LF}" 2>/dev/null || true
git notes show >actual 2>/dev/null || true

true
