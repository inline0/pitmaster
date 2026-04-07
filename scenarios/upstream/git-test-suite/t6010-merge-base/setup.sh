#!/bin/bash
set -e

git init .
git config user.email "test@test.com"
git config user.name "Test"
git config init.defaultBranch main
echo $IH >expected
git merge-base --independent IB IH >actual 2>/dev/null || true
git checkout MM1 2>/dev/null || true
git checkout MMR 2>/dev/null || true
git merge-base --all MMA MMB MMC >actual 2>/dev/null || true
git merge-base --all --octopus MMA MMB MMC >actual.common 2>/dev/null || true
git merge -s ours --allow-unrelated-histories CC1 2>/dev/null || true
git merge -s ours --allow-unrelated-histories CC2 2>/dev/null || true
git merge-base --all CCB CCA^^ CCA^^2 >actual 2>/dev/null || true
git checkout -b base $E 2>/dev/null || true
git commit --allow-empty -m "Base commit #$count" 2>/dev/null || true
git checkout -B derived 2>/dev/null || true
git commit --allow-empty -m "Derived #$count" 2>/dev/null || true
git checkout -B base $E || exit 1 2>/dev/null || true
git merge-base --fork-point base $(cat derived$count) >actual 2>/dev/null || true
git checkout derived 2>/dev/null || true
git merge-base --fork-point base >actual 2>/dev/null || true
git merge-base --fork-point no-reflog derived 2>/dev/null || true
git merge-base --all --octopus JAA JDD JE >actual 2>/dev/null || true
