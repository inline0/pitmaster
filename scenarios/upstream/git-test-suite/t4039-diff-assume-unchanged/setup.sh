#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo zero > zero
git add zero 2>/dev/null || true
git commit -m zero 2>/dev/null || true
echo one > one
echo two > two
git add one two 2>/dev/null || true
git commit -m onetwo 2>/dev/null || true
echo borked >> one
echo content >exec
git add exec 2>/dev/null || true
git commit -m exec 2>/dev/null || true
