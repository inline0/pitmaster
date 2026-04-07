#!/bin/bash

git init . >/dev/null 2>&1
git config user.email "test@test.com"
git config user.name "Test"
git config protocol.file.allow always 2>/dev/null || true

source '/Users/dennis/Local Sites/fabrikat/inline0/pitmaster/bin/git-test-shim.sh'

git init repo 2>/dev/null || true
git commit --allow-empty -m initial 2>/dev/null || true
git checkout -b default-branch 2>/dev/null || true
git tag default-tag 2>/dev/null || true
git tag multi_hierarchy/default-tag 2>/dev/null || true
cp $branch_dir_prefix/default-branch $branch_dir_prefix/@ 2>/dev/null || true
cp $tag_dir_prefix/default-tag $tag_dir_prefix/tag-1.lock 2>/dev/null || true
cp $tag_dir_prefix/default-tag $tag_dir_prefix/.lock 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cp $branch_dir_prefix/default-branch "$branch_dir_prefix/$refname" 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cp $tag_dir_prefix/default-tag "$tag_dir_prefix/$refname" 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cp $tag_dir_prefix/multi_hierarchy/default-tag "$tag_dir_prefix/multi_hierarchy/$refname" 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
mkdir "$branch_dir_prefix/$refname" 2>/dev/null || true
cp $branch_dir_prefix/default-branch "$branch_dir_prefix/$refname/default-branch" 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
git init repo 2>/dev/null || true
git commit --allow-empty -m initial 2>/dev/null || true
git checkout -b branch-1 2>/dev/null || true
cp $branch_dir_prefix/branch-1 $branch_dir_prefix/.branch-1 2>/dev/null || true
cat >expect <<-EOF 2>/dev/null || true
cp $branch_dir_prefix/branch-1 $branch_dir_prefix/.branch-1 2>/dev/null || true
git init repo 2>/dev/null || true
test_commit initial 2>/dev/null || true
git worktree add --detach ./worktree 2>/dev/null || true

true
