#!/bin/bash
set -e

for path in \
    .git/HEAD \
    .git/config \
    .git/description \
    .git/hooks \
    .git/info \
    .git/info/exclude \
    .git/objects \
    .git/objects/info \
    .git/objects/pack \
    .git/refs \
    .git/refs/heads \
    .git/refs/tags
do
    if [ -d "$path" ]; then
        printf 'dir %s\n' "$path"
    elif [ -f "$path" ]; then
        printf 'file %s\n' "$path"
    else
        printf 'missing %s\n' "$path"
    fi
done
