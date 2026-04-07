#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

mkdir "$FN" 2>/dev/null || true
git add . 2>/dev/null || true
git commit -q -m Initial 2>/dev/null || true
git commit -a -m Second 2>/dev/null || true
cat >expect.quoted <<\EOF 2>/dev/null || true
cat >expect.raw <<EOF 2>/dev/null || true

true
