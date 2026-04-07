#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m Initial 2>/dev/null || true
git commit --allow-empty -m Second 2>/dev/null || true
git commit --allow-empty -m Third 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git symbolic-ref $prefix/symref $prefix/foo 2>/dev/null || true
git symbolic-ref $prefix/symref $prefix/foo 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git symbolic-ref $prefix/symref $prefix/foo 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git symbolic-ref $prefix/symref $prefix/foo 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git symbolic-ref $prefix/symref $prefix/foo 2>/dev/null || true
git symbolic-ref $prefix/symref $prefix/foo 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git symbolic-ref $prefix/symref $prefix/foo 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
git symbolic-ref $prefix/symref $prefix/foo 2>/dev/null || true
git update-ref $prefix/foo $C 2>/dev/null || true
