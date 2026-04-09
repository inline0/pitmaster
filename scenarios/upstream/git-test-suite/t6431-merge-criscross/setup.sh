#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

PITMASTER_ROOT="${PITMASTER_ROOT:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
source "${PITMASTER_ROOT}/bin/git-test-shim.sh"

mkdir data 2>/dev/null || true
echo $n > data/$n 2>/dev/null || true
git add data 2>/dev/null || true
git commit -m A 2>/dev/null || true
git branch A 2>/dev/null || true
git checkout -b B A 2>/dev/null || true
git rm data/9 2>/dev/null || true
git add data 2>/dev/null || true
git commit -m B 2>/dev/null || true
git branch D 2>/dev/null || true
git checkout D 2>/dev/null || true
echo testD > data/testD 2>/dev/null || true
git add data 2>/dev/null || true
git commit -m D 2>/dev/null || true
git checkout -b C A 2>/dev/null || true
git mv data/9 data/new-9 2>/dev/null || true
git add data 2>/dev/null || true
git commit -m C 2>/dev/null || true
git branch E 2>/dev/null || true
git checkout E 2>/dev/null || true
echo testE > data/testE 2>/dev/null || true
git add data 2>/dev/null || true
git commit -m E 2>/dev/null || true
git checkout B 2>/dev/null || true
git add data 2>/dev/null || true
git commit -m F 2>/dev/null || true
git branch F 2>/dev/null || true
git checkout C 2>/dev/null || true
git add data 2>/dev/null || true
git commit -m G 2>/dev/null || true
git branch G 2>/dev/null || true
git merge F 2>/dev/null || true

true
