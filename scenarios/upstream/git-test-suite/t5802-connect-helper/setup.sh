#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git config --global protocol.ext.allow user 2>/dev/null || true
git commit --allow-empty -m initial 2>/dev/null || true
git commit --allow-empty -m second 2>/dev/null || true
git commit --allow-empty -m third 2>/dev/null || true
git tag -a -m "tip three" three 2>/dev/null || true
git commit --allow-empty -m fourth 2>/dev/null || true
git config remote.origin.url "ext::sh -c $cmd" 2>/dev/null || true
git commit --allow-empty -m fifth 2>/dev/null || true
git tag -a -m "tip five" five 2>/dev/null || true
git commit --allow-empty -m sixth 2>/dev/null || true
git tag -a -m "tip two" two three^1 2>/dev/null || true
git tag -a -m "tip one " one two^1 2>/dev/null || true
mkdir remote
mkdir remote/host
