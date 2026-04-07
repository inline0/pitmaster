#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git checkout -b foo 2>/dev/null || true
git checkout -b bar 2>/dev/null || true
git checkout -b ambiguous_branch_and_file 2>/dev/null || true
git checkout -b foo 2>/dev/null || true
git checkout -b baz 2>/dev/null || true
git checkout -b ambiguous_branch_and_file 2>/dev/null || true
git config remote.repo_b.fetch \ 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -b t_ambiguous_branch_and_file 2>/dev/null || true
git add ambiguous_branch_and_file 2>/dev/null || true
git commit -m "ambiguous_branch_and_file" 2>/dev/null || true
echo "file contents" >ambiguous_branch_and_file
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -p foo 2>stderr 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout bar 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout baz 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -b bar 2>/dev/null || true
git checkout -b spam 2>/dev/null || true
git checkout -b baz 2>/dev/null || true
git checkout -b eggs 2>/dev/null || true
git config remote.repo_c.fetch \ 2>/dev/null || true
git config remote.repo_d.fetch \ 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout spam 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout eggs 2>/dev/null || true
git checkout -B main 2>/dev/null || true
git checkout -B main 2>/dev/null || true
mkdir sub
git checkout -B main 2>/dev/null || true
git checkout spam -- 2>/dev/null || true
git checkout main 2>/dev/null || true
git branch strict 2>/dev/null || true
git branch loose 2>/dev/null || true
git commit --allow-empty -m "a bit more" 2>/dev/null || true
git checkout strict >expect.raw 2>&1 2>/dev/null || true
git checkout loose >actual.raw 2>&1 2>/dev/null || true
git update-ref refs/remotes/foo/dwim-arg HEAD 2>/dev/null || true
echo foo >dwim-arg
git add dwim-arg 2>/dev/null || true
echo bar >dwim-arg
git update-ref refs/remotes/foo/dwim-arg1 HEAD 2>/dev/null || true
echo foo >dwim-arg1
git add dwim-arg1 2>/dev/null || true
echo bar >dwim-arg1
git checkout -- dwim-arg1 2>/dev/null || true
git update-ref refs/remotes/foo/dwim-arg2 HEAD 2>/dev/null || true
echo foo >dwim-arg2
git add dwim-arg2 2>/dev/null || true
echo bar >dwim-arg2
git checkout dwim-arg2 -- 2>/dev/null || true
