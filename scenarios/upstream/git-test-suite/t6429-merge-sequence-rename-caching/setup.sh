#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git init caching-renames-and-new-renames 2>/dev/null || true
git add numbers values 2>/dev/null || true
git commit -m orig 2>/dev/null || true
git branch upstream 2>/dev/null || true
git branch topic 2>/dev/null || true
git switch upstream 2>/dev/null || true
git add numbers values 2>/dev/null || true
git commit -m "Tweaked both files" 2>/dev/null || true
git switch topic 2>/dev/null || true
git add numbers 2>/dev/null || true
git mv numbers sequence 2>/dev/null || true
git commit -m A 2>/dev/null || true
git add values 2>/dev/null || true
git mv values scruples 2>/dev/null || true
git commit -m B 2>/dev/null || true
git switch upstream 2>/dev/null || true
git update-ref --stdin <out 2>/dev/null || true
git checkout topic 2>/dev/null || true
git init pick-commit-and-its-immediate-revert 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m orig 2>/dev/null || true
git branch upstream 2>/dev/null || true
git branch topic 2>/dev/null || true
git switch upstream 2>/dev/null || true
git add numbers 2>/dev/null || true
git mv numbers sequence 2>/dev/null || true
git commit -m "Renamed (and modified) numbers -> sequence" 2>/dev/null || true
git switch topic 2>/dev/null || true
git add numbers 2>/dev/null || true
git commit -m A 2>/dev/null || true
git revert HEAD 2>/dev/null || true
git switch upstream 2>/dev/null || true
git update-ref --stdin <out 2>/dev/null || true
git checkout topic 2>/dev/null || true
git init rename-rename-1to1-then-add-old-filename 2>/dev/null || true
git add sequence 2>/dev/null || true
git commit -m orig 2>/dev/null || true
git branch upstream 2>/dev/null || true
git branch topic 2>/dev/null || true
git switch upstream 2>/dev/null || true
git add sequence 2>/dev/null || true
git mv sequence values 2>/dev/null || true
git commit -m "Renamed (and modified) sequence -> values" 2>/dev/null || true
git switch topic 2>/dev/null || true
git add sequence 2>/dev/null || true
git mv sequence values 2>/dev/null || true
git commit -m A 2>/dev/null || true
test_write_lines A B C D E F G H I J >sequence 2>/dev/null || true
git add sequence 2>/dev/null || true
git commit -m B 2>/dev/null || true
git switch upstream 2>/dev/null || true
git update-ref --stdin <out 2>/dev/null || true
git checkout topic 2>/dev/null || true

true
