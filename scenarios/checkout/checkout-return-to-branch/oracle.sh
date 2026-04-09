#!/usr/bin/env bash
set -euo pipefail

git checkout "$(cat .detach-id)" >/dev/null 2>&1
git checkout main >/dev/null 2>&1
