#!/usr/bin/env bash
set -euo pipefail

git revert "$(cat .revert-id)" >/dev/null 2>&1 || true
