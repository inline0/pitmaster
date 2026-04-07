#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit 1st 2>/dev/null || true
test_commit 2nd 2>/dev/null || true
test_commit 3rd 2>/dev/null || true
test_commit 4th 2>/dev/null || true
test_commit 5th 2>/dev/null || true
test_commit 6th 2>/dev/null || true
test_commit 7th 2>/dev/null || true
test_commit 8th 2>/dev/null || true
test_commit 9th 2>/dev/null || true
test_commit 10th 2>/dev/null || true
test_commit 11th 2>/dev/null || true
test_commit 12th 2>/dev/null || true
test_commit 13th 2>/dev/null || true
test_commit 14th 2>/dev/null || true
test_commit 15th 2>/dev/null || true
git config core.notesRef refs/notes/x 2>/dev/null || true
git notes add -m "x notes on 6th commit" 6th 2>/dev/null || true
git notes add -m "x notes on 7th commit" 7th 2>/dev/null || true
git notes add -m "x notes on 8th commit" 8th 2>/dev/null || true
git notes add -m "x notes on 9th commit" 9th 2>/dev/null || true
git notes add -m "x notes on 10th commit" 10th 2>/dev/null || true
git notes add -m "x notes on 11th commit" 11th 2>/dev/null || true
git notes add -m "x notes on 12th commit" 12th 2>/dev/null || true
git notes add -m "x notes on 13th commit" 13th 2>/dev/null || true
git notes add -m "x notes on 14th commit" 14th 2>/dev/null || true
git notes add -m "x notes on 15th commit" 15th 2>/dev/null || true
git update-ref refs/notes/y refs/notes/x 2>/dev/null || true
git config core.notesRef refs/notes/y 2>/dev/null || true
git notes add -f -m "y notes on 3rd commit" 3rd 2>/dev/null || true
git notes add -f -m "y notes on 4th commit" 4th 2>/dev/null || true
git notes add -f -m "y notes on 5th commit" 5th 2>/dev/null || true
git notes remove 6th 2>/dev/null || true
git notes remove 7th 2>/dev/null || true
git notes remove 8th 2>/dev/null || true
git notes add -f -m "y notes on 12th commit" 12th 2>/dev/null || true
git notes add -f -m "y notes on 13th commit" 13th 2>/dev/null || true
git notes add -f -m "y notes on 14th commit" 14th 2>/dev/null || true
git notes add -f -m "y notes on 15th commit" 15th 2>/dev/null || true

true
