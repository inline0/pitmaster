#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

echo ignored >ignored 2>/dev/null || true
mkdir .git/info 2>/dev/null || true
echo ignored export-ignore >>.git/info/attributes 2>/dev/null || true
git add ignored 2>/dev/null || true
mkdir not-ignored-dir 2>/dev/null || true
echo ignored-in-tree >not-ignored-dir/ignored 2>/dev/null || true
echo not-ignored-in-tree >not-ignored-dir/ignored-only-if-dir 2>/dev/null || true
git add not-ignored-dir 2>/dev/null || true
mkdir ignored-only-if-dir 2>/dev/null || true
echo ignored by ignored dir >ignored-only-if-dir/ignored-by-ignored-dir 2>/dev/null || true
echo ignored-only-if-dir/ export-ignore >>.git/info/attributes 2>/dev/null || true
git add ignored-only-if-dir 2>/dev/null || true
mkdir -p ignored-without-slash 2>/dev/null || true
echo "ignored without slash" >ignored-without-slash/foo 2>/dev/null || true
git add ignored-without-slash/foo 2>/dev/null || true
echo "ignored-without-slash export-ignore" >>.git/info/attributes 2>/dev/null || true
mkdir -p wildcard-without-slash 2>/dev/null || true
echo "ignored without slash" >wildcard-without-slash/foo 2>/dev/null || true
git add wildcard-without-slash/foo 2>/dev/null || true
echo "wild*-without-slash export-ignore" >>.git/info/attributes 2>/dev/null || true
mkdir -p deep/and/slashless 2>/dev/null || true
echo "ignored without slash" >deep/and/slashless/foo 2>/dev/null || true
git add deep/and/slashless/foo 2>/dev/null || true
echo "deep/and/slashless export-ignore" >>.git/info/attributes 2>/dev/null || true
mkdir -p deep/with/wildcard 2>/dev/null || true
echo "ignored without slash" >deep/with/wildcard/foo 2>/dev/null || true
git add deep/with/wildcard/foo 2>/dev/null || true
echo "deep/*t*/wildcard export-ignore" >>.git/info/attributes 2>/dev/null || true
mkdir -p one-level-lower/two-levels-lower/ignored-only-if-dir 2>/dev/null || true
echo ignored by ignored dir >one-level-lower/two-levels-lower/ignored-only-if-dir/ignored-by-ignored-dir 2>/dev/null || true
git add one-level-lower 2>/dev/null || true
git commit -m. 2>/dev/null || true
mkdir bare/info 2>/dev/null || true
cp .git/info/attributes bare/info/attributes 2>/dev/null || true

true
