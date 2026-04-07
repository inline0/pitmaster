#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git config alias.lgf "log --format=%s --first-parent" 2>/dev/null || true
git commit --allow-empty -m "a single log entry" 2>/dev/null || true
echo "a single log entry" >expect 2>/dev/null || true
echo "distimdistim was called" >expect 2>/dev/null || true
git config help.autocorrect $show 2>/dev/null || true
git config help.autocorrect $immediate 2>/dev/null || true
echo "a single log entry" >expect 2>/dev/null || true
echo "distimdistim was called" >expect 2>/dev/null || true

true
