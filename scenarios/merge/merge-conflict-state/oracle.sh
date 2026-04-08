#!/usr/bin/env bash
set -euo pipefail

git merge feature >/dev/null 2>&1 || true
