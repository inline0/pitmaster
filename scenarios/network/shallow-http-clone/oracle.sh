#!/usr/bin/env bash
set -euo pipefail

url="$(cat .remote-url)"
git clone --depth=1 "$url" git-clone >/dev/null 2>&1

{
    printf 'is_shallow=%s\n' "$(git -C git-clone rev-parse --is-shallow-repository)"
    printf 'head=%s\n' "$(git -C git-clone rev-parse HEAD)"
    printf 'commit_count=%s\n' "$(git -C git-clone rev-list --count HEAD)"
    printf 'shallow=%s\n' "$(tr -d '\n' < git-clone/.git/shallow)"
} > .shallow-state
