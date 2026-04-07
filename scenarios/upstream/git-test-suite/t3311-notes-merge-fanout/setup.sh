#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config core.notesRef refs/notes/x 2>/dev/null || true
test_commit "commit$i" >/dev/null 2>/dev/null || true
git notes add -m "notes for commit$i" || return 1 2>/dev/null || true
git update-ref refs/notes/y refs/notes/x 2>/dev/null || true
git config core.notesRef refs/notes/y 2>/dev/null || true
test_commit_bulk --start=6 --id=commit $((num - 5)) 2>/dev/null || true
git notes add -m "notes for commit$i" HEAD~$i || return 1 2>/dev/null || true

true
