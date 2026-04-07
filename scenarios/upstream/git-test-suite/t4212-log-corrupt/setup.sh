#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit foo 2>/dev/null || true
git hash-object --literally -w -t commit broken_email.commit >broken_email.hash 2>/dev/null || true
git update-ref refs/heads/broken_email $(cat broken_email.hash) 2>/dev/null || true
echo "Author: A U Thor <author@example.com>" 2>/dev/null || true

true
