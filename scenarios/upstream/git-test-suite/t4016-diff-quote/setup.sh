#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo P0.0 >"$P0.0"
echo P0.1 >"$P0.1"
echo P0.2 >"$P0.2"
echo P0.3 >"$P0.3"
echo P1.0 >"$P1.0"
echo P1.2 >"$P1.2"
echo P1.3 >"$P1.3"
git add . 2>/dev/null || true
git commit -m initial 2>/dev/null || true
git mv "$P0.0" "R$P0.0" 2>/dev/null || true
git mv "$P0.1" "R$P1.0" 2>/dev/null || true
git mv "$P0.2" "R$P2.0" 2>/dev/null || true
git mv "$P0.3" "R$P3.0" 2>/dev/null || true
git mv "$P1.0" "R$P0.1" 2>/dev/null || true
git mv "$P1.2" "R$P2.1" 2>/dev/null || true
git mv "$P1.3" "R$P3.1" 2>/dev/null || true
