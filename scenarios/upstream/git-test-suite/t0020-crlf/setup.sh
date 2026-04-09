#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

git config core.autocrlf input 2>/dev/null || true
git config core.safecrlf true 2>/dev/null || true
test_write_lines I am all CRLF | append_cr >allcrlf 2>/dev/null || true
git config core.autocrlf input 2>/dev/null || true
git config core.safecrlf true 2>/dev/null || true
test_write_lines Oh here is CRLFQ in text | q_to_cr >mixed 2>/dev/null || true
git config core.autocrlf true 2>/dev/null || true
git config core.safecrlf true 2>/dev/null || true
test_write_lines I am all LF >alllf 2>/dev/null || true
git config core.autocrlf true 2>/dev/null || true
git config core.safecrlf true 2>/dev/null || true
test_write_lines Oh here is CRLFQ in text | q_to_cr >mixed 2>/dev/null || true
git config core.autocrlf input 2>/dev/null || true
git config core.safecrlf warn 2>/dev/null || true
test_write_lines I am all LF >doublewarn 2>/dev/null || true
git add doublewarn 2>/dev/null || true
git commit -m "nowarn" 2>/dev/null || true
test_write_lines Oh here is CRLFQ in text | q_to_cr >doublewarn 2>/dev/null || true
git add doublewarn 2>err 2>/dev/null || true

true
