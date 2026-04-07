#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

test_commit first 2>/dev/null || true
git init new 2>/dev/null || true
git tag -s -m "OpenPGP signed tag" openpgp-signed first 2>/dev/null || true
OPENPGP_SIGNED=$(git rev-parse --verify refs/tags/openpgp-signed) 2>/dev/null || true

true
