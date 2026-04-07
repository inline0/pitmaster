#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config alias.lgf "log --format=%s --first-parent" 2>/dev/null || true
git commit --allow-empty -m "a single log entry" 2>/dev/null || true
echo "a single log entry" >expect
echo "distimdistim was called" >expect
git config help.autocorrect $show 2>/dev/null || true
git config help.autocorrect $immediate 2>/dev/null || true
echo "a single log entry" >expect
echo "distimdistim was called" >expect
git config help.autocorrect never 2>/dev/null || true
