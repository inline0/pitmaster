#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
git checkout -b main 2>/dev/null || true
mkdir sub
git commit -m "add submodule" 2>/dev/null || true
git config -f .gitmodules submodule.sub.shallow true 2>/dev/null || true
git add .gitmodules 2>/dev/null || true
git commit -m "recommend shallow for sub" 2>/dev/null || true
git config -f .gitmodules submodule.sub.shallow false 2>/dev/null || true
git add .gitmodules 2>/dev/null || true
git commit -m "recommend non shallow for sub" 2>/dev/null || true
