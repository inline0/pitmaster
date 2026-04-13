#!/usr/bin/env bash
set -euo pipefail

config_path=${1:?config path required}
fetch=$(git config --file "$config_path" --get-all remote.origin.fetch | paste -sd '|' -)

printf 'alias.keep=%s\n' "$(git config --file "$config_path" --get alias.keep || true)"
printf 'alias.drop=%s\n' "$(git config --file "$config_path" --get alias.drop || true)"
printf 'remote.origin.url=%s\n' "$(git config --file "$config_path" --get remote.origin.url || true)"
printf 'remote.origin.fetch=%s\n' "$fetch"
