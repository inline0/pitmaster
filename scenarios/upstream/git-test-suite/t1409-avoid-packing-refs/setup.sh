#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git commit --allow-empty -m "Commit A" 2>/dev/null || true
git commit --allow-empty -m "Commit B" 2>/dev/null || true
git commit --allow-empty -m "Commit C" 2>/dev/null || true
git update-ref refs/heads/foo $A 2>/dev/null || true
git update-ref refs/heads/foo $B 2>/dev/null || true
git update-ref refs/heads/foo $C $B 2>/dev/null || true
git update-ref -d refs/heads/foo 2>/dev/null || true
git update-ref refs/heads/packed-update $A 2>/dev/null || true
git update-ref refs/heads/packed-update $B 2>/dev/null || true
git update-ref refs/heads/packed-checked-update $A 2>/dev/null || true
git update-ref refs/heads/packed-checked-update $B $A 2>/dev/null || true
git update-ref refs/heads/packed-verify $A 2>/dev/null || true
git update-ref refs/heads/packed-delete $A 2>/dev/null || true
git update-ref -d refs/heads/packed-delete 2>/dev/null || true
git update-ref refs/heads/loose-update $A 2>/dev/null || true
git update-ref refs/heads/loose-update $B 2>/dev/null || true
git update-ref refs/heads/loose-checked-update $A 2>/dev/null || true
git update-ref refs/heads/loose-checked-update $B $A 2>/dev/null || true
git update-ref refs/heads/loose-verify $A 2>/dev/null || true
git update-ref refs/heads/loose-delete $A 2>/dev/null || true
git update-ref -d refs/heads/loose-delete 2>/dev/null || true
