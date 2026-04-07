#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init midx-compact-lex-order 2>/dev/null || true
(
cd midx-compact-lex-order 2>/dev/null || true
git config maintenance.auto false 2>/dev/null || true
)
git init midx-compact-non-lex-order 2>/dev/null || true
(
cd midx-compact-non-lex-order 2>/dev/null || true
git config maintenance.auto false 2>/dev/null || true
)
git init midx-compact-bogus 2>/dev/null || true
(
cd midx-compact-bogus 2>/dev/null || true
git config maintenance.auto false 2>/dev/null || true
)
(
cd midx-compact-bogus 2>/dev/null || true
)
(
cd midx-compact-bogus 2>/dev/null || true
)
(
cd midx-compact-bogus 2>/dev/null || true
)
(
cd midx-compact-bogus 2>/dev/null || true
)
git init midx-compact-preserve-selection 2>/dev/null || true
(
cd midx-compact-preserve-selection 2>/dev/null || true
git config maintenance.auto false 2>/dev/null || true
test_commit A 2>/dev/null || true
test_commit B 2>/dev/null || true
)

true
