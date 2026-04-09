#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

printf "a\nb\nc\n" >pre 2>/dev/null || true
printf "c\na\nb\n" >post 2>/dev/null || true
printf "a\nb\nc\nd\ne\nf\n" >pre 2>/dev/null || true
printf "c\na\nb\nf\nd\ne\n" >post 2>/dev/null || true
printf "a\nb\nc\n" >pre 2>/dev/null || true
printf "c\na\nb\n" >post 2>/dev/null || true
printf "a\nb\nc\nd\ne\nc\n" >pre 2>/dev/null || true
printf "c\na\nb\nc\nd\ne\n" >post 2>/dev/null || true
printf "a\nb\nc\n" >pre 2>/dev/null || true
printf "c\na\nb\n" >post 2>/dev/null || true
mv post expected_post 2>/dev/null || true
printf "a\nb\nc\n" >pre 2>/dev/null || true
printf "c\na\nb\n" >post 2>/dev/null || true
printf "a\nb\nc\n" >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m foo 2>/dev/null || true
printf "c\na\nb\n" >file 2>/dev/null || true
git add file 2>/dev/null || true
git commit -m foo 2>/dev/null || true

true
