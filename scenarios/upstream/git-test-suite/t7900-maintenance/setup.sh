#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

(
mkdir -p $XDG_CONFIG_HOME/git 2>/dev/null || true
git config --file=$XDG_CONFIG_HOME/git/config --get maintenance.repo >actual 2>/dev/null || true
)
git config --global --get maintenance.repo >actual 2>/dev/null || true
(
mkdir -p $XDG_CONFIG_HOME/git 2>/dev/null || true
)
git config maintenance.gc.enabled false 2>/dev/null || true
git config maintenance.commit-graph.enabled true 2>/dev/null || true
test_commit first 2>/dev/null || true
git commit --allow-empty -m "second" 2>/dev/null || true
git commit --allow-empty -m "third" 2>/dev/null || true

true
