#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo hello >greetings 2>/dev/null || true
git add greetings 2>/dev/null || true
git commit -m greetings 2>/dev/null || true
S=$(git rev-parse :greetings | sed -e "s|^..|&/|") 2>/dev/null || true
echo $S >S 2>/dev/null || true
echo $X >X 2>/dev/null || true
cp .git/objects/$S .git/objects/$S.back 2>/dev/null || true
mv -f .git/objects/$X .git/objects/$S 2>/dev/null || true
git init dst 2>/dev/null || true
git config fetch.fsckobjects false 2>/dev/null || true
git config transfer.fsckobjects false 2>/dev/null || true
git init dst 2>/dev/null || true
git config fetch.fsckobjects false 2>/dev/null || true
git config transfer.fsckobjects true 2>/dev/null || true

true
