#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git add empty 2>/dev/null || true
git commit -q -a -m Initial 2>/dev/null || true
printf '%s\n' "$2" | tr '|' '\012' >expected
mkdir A B C D E F
echo hello1 >A/newfile1.txt
echo hello2 >B/newfile2.txt
git add A/newfile1.txt 2>/dev/null || true
git add B/newfile2.txt 2>/dev/null || true
git add C/newfile3.png 2>/dev/null || true
git add D/newfile4.png 2>/dev/null || true
git commit -a -m "Test: New file" 2>/dev/null || true
echo Hello1 >>A/newfile1.txt
echo Hello5  >E/newfile5.txt
git add E/newfile5.txt 2>/dev/null || true
git add F/newfile6.png 2>/dev/null || true
git commit -a -m "Test: Remove, add and update" 2>/dev/null || true
git commit -a -m "generatiion 1" 2>/dev/null || true
git commit -a -m "generation 2" 2>/dev/null || true
git commit -a -m "test: remove only a binary file" 2>/dev/null || true
git commit -a -m "test: remove only a binary file" 2>/dev/null || true
mkdir "G g"
echo ok then >"G g/with spaces.txt"
git add "G g/with spaces.txt"  \ 2>/dev/null || true
git add "G g/with spaces.png" 2>/dev/null || true
git commit -a -m "With spaces" 2>/dev/null || true
echo Ok then >>"G g/with spaces.txt"
git add "G g/with spaces.png" 2>/dev/null || true
git commit -a -m "Update with spaces" 2>/dev/null || true
mkdir -p "tst/$p"
mkdir -p Å/goo/a/b/c/d/e/f/g/h/i/j/k/l/m/n/o/p/q/r/s/t/u/v/w/x/y/z/å/ä/ö
echo Foo >Å/goo/a/b/c/d/e/f/g/h/i/j/k/l/m/n/o/p/q/r/s/t/u/v/w/x/y/z/å/ä/ö/gårdetsågårdet.txt
git add Å/goo/a/b/c/d/e/f/g/h/i/j/k/l/m/n/o/p/q/r/s/t/u/v/w/x/y/z/å/ä/ö/gårdetsågårdet.txt 2>/dev/null || true
git add Å/goo/a/b/c/d/e/f/g/h/i/j/k/l/m/n/o/p/q/r/s/t/u/v/w/x/y/z/å/ä/ö/gårdetsågårdet.png 2>/dev/null || true
git commit -a -m "Går det så går det"  \ 2>/dev/null || true
git add "E/newfile5.txt" 2>/dev/null || true
git commit -a -m "Update one" 2>/dev/null || true
git add "E/newfile5.txt" 2>/dev/null || true
git commit -a -m "Update two" 2>/dev/null || true
mkdir G
echo executeon >G/on
echo executeoff >G/off
git add G/on 2>/dev/null || true
git add G/off 2>/dev/null || true
git commit -a -m "Execute test" 2>/dev/null || true
mkdir W
echo foobar >W/file1.txt
echo bazzle >W/file2.txt
git add W/file1.txt 2>/dev/null || true
git add W/file2.txt 2>/dev/null || true
git commit -m "More updates" 2>/dev/null || true
echo Notes > release-notes
git add release-notes 2>/dev/null || true
git commit -m "Add release notes" release-notes 2>/dev/null || true
echo new > DS
echo new > E/DS
echo modified > release-notes
git add DS E/DS release-notes 2>/dev/null || true
git commit -m "Add two files with the same basename" 2>/dev/null || true
git add attic_gremlin 2>/dev/null || true
git commit -m "Added attic_gremlin" 2>/dev/null || true
echo space > " space"
git add " space" 2>/dev/null || true
git commit -m "Add a file with a leading space" 2>/dev/null || true
git add " space" 2>/dev/null || true
git commit -m "fake initial commit" 2>/dev/null || true
echo Hello >> " space"
git commit -m "Another change" " space" 2>/dev/null || true
