#!/usr/bin/env bash
set -euo pipefail

printf 'core.editor=%s\n' "$(git config --includes --file .git/config --get core.editor)"
printf 'alias.lg=%s\n' "$(git config --includes --file .git/config --get alias.lg)"
