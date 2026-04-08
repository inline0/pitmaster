#!/usr/bin/env bash
set -euo pipefail

git rebase main >/dev/null 2>&1 || true
git rebase --skip >/dev/null
