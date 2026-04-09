#!/usr/bin/env bash
set -euo pipefail

git bisect start "$(git rev-parse bad)" "$(git rev-parse good)" >/dev/null
git bisect good >/dev/null
git bisect bad >/dev/null
