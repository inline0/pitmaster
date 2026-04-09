#!/usr/bin/env bash
set -euo pipefail

config_path="$1"
fetch_values="$(git config --file "$config_path" --get-all remote.origin.fetch | paste -sd'|' -)"

printf 'core.filemode=%s\n' "$(git config --file "$config_path" --get core.filemode)"
printf 'core.logallrefupdates=%s\n' "$(git config --file "$config_path" --get core.logAllRefUpdates)"
printf 'alias.lg=%s\n' "$(git config --file "$config_path" --get alias.lg)"
printf 'remote.origin.url=%s\n' "$(git config --file "$config_path" --get remote.origin.url)"
printf 'remote.origin.fetch=%s\n' "$fetch_values"
printf 'branch.main.merge=%s\n' "$(git config --file "$config_path" --get branch.main.merge)"
