#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git checkout -f --orphan initial-branch 2>/dev/null || true
git config core.autocrlf false 2>/dev/null || true
echo x >file_x
echo y >file_y
echo z >file_z
mkdir dir1
echo a >dir1/file_a
echo b >dir1/file_b
git add file_x file_y file_z dir1 2>/dev/null || true
git commit -m initial 2>/dev/null || true
echo x >>file_x
git stash -- file_x 2>/dev/null || true
git stash 2>/dev/null || true
git add file_x 2>/dev/null || true
git rm file_z 2>/dev/null || true
git mv file_y renamed_y 2>/dev/null || true
git commit -m second 2>/dev/null || true
echo x.ign >.gitignore
echo "ignore me" >x.ign
echo x.ign >.gitignore
echo "ignore me" >x.ign
git add .gitignore 2>/dev/null || true
git commit -m ignore_trash 2>/dev/null || true
echo test >intent2.add
git add --intent-to-add intent1.add intent2.add 2>/dev/null || true
git branch AA_A initial-branch 2>/dev/null || true
git checkout AA_A 2>/dev/null || true
echo "Branch AA_A" >conflict.txt
git add conflict.txt 2>/dev/null || true
git commit -m "branch aa_a" 2>/dev/null || true
git branch AA_B initial-branch 2>/dev/null || true
git checkout AA_B 2>/dev/null || true
echo "Branch AA_B" >conflict.txt
git add conflict.txt 2>/dev/null || true
git commit -m "branch aa_b" 2>/dev/null || true
git branch AA_M AA_B 2>/dev/null || true
git checkout AA_M 2>/dev/null || true
git branch UU_ANC initial-branch 2>/dev/null || true
git checkout UU_ANC 2>/dev/null || true
echo "Ancestor" >conflict.txt
git add conflict.txt 2>/dev/null || true
git commit -m "UU_ANC" 2>/dev/null || true
git branch UU_A UU_ANC 2>/dev/null || true
git checkout UU_A 2>/dev/null || true
echo "Branch UU_A" >conflict.txt
git add conflict.txt 2>/dev/null || true
git commit -m "branch uu_a" 2>/dev/null || true
git branch UU_B UU_ANC 2>/dev/null || true
git checkout UU_B 2>/dev/null || true
echo "Branch UU_B" >conflict.txt
git add conflict.txt 2>/dev/null || true
git commit -m "branch uu_b" 2>/dev/null || true
git branch UU_M UU_B 2>/dev/null || true
git checkout UU_M 2>/dev/null || true
git checkout initial-branch 2>/dev/null || true
echo xyz >file_xyz
git add file_xyz 2>/dev/null || true
git commit -m xyz 2>/dev/null || true
git update-ref -d refs/remotes/origin/initial-branch 2>/dev/null || true
git checkout initial-branch 2>/dev/null || true
echo xyz >file_xyz
git add file_xyz 2>/dev/null || true
git commit -m xyz 2>/dev/null || true
git checkout initial-branch 2>/dev/null || true
echo "xxxx" >file_in_sub
git add file_in_sub 2>/dev/null || true
echo "more changes" >>file_in_sub
git add file_in_sub 2>/dev/null || true
echo "yyyy" >>another_file_in_sub
git add file_in_sub 2>/dev/null || true
git commit -m "new commit" 2>/dev/null || true
git add sub1 2>/dev/null || true
git commit -m "super commit" 2>/dev/null || true
echo "zzzz" >>file_in_sub
