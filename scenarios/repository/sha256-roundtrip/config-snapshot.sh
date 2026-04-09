#!/bin/bash
set -e

for key in \
    core.repositoryformatversion \
    core.filemode \
    core.bare \
    core.ignorecase \
    core.precomposeunicode \
    extensions.objectformat
do
    if value=$(git config --local --get "$key" 2>/dev/null); then
        printf '%s=%s\n' "$key" "$value"
    fi
done
