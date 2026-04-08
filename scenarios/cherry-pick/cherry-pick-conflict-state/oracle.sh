#!/usr/bin/env bash
set -euo pipefail

git cherry-pick "$(cat .pick-id)" >/dev/null 2>&1 || true
