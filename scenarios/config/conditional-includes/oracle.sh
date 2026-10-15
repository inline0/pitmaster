#!/usr/bin/env bash
set -euo pipefail
git config --includes --get-all demo.value > .git/conditional-values
