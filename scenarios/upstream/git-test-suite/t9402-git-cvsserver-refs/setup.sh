#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

echo "Simple text file" >textfile.c 2>/dev/null || true
echo "t2" >t2 2>/dev/null || true
mkdir adir 2>/dev/null || true
echo "adir/afile line1" >adir/afile 2>/dev/null || true
echo "adir/afile line2" >>adir/afile 2>/dev/null || true
echo "adir/afile line3" >>adir/afile 2>/dev/null || true
echo "adir/afile line4" >>adir/afile 2>/dev/null || true
echo "adir/a2file" >>adir/a2file 2>/dev/null || true
mkdir adir/bdir 2>/dev/null || true
echo "adir/bdir/bfile line 1" >adir/bdir/bfile 2>/dev/null || true
echo "adir/bdir/bfile line 2" >>adir/bdir/bfile 2>/dev/null || true
echo "adir/bdir/b2file" >adir/bdir/b2file 2>/dev/null || true
git add textfile.c t2 adir 2>/dev/null || true
git commit -q -m "First Commit (v1)" 2>/dev/null || true
git tag v1 2>/dev/null || true
git branch b1 2>/dev/null || true
(
cd cvswork3 2>/dev/null || true
sed -e "s/line1/line1 - data/" adir/afile >adir/afileNEW 2>/dev/null || true
mv -f adir/afileNEW adir/afile 2>/dev/null || true
echo "afile5" >adir/afile5 2>/dev/null || true
rm t2 2>/dev/null || true
)

true
