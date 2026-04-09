#!/usr/bin/env bash
set -euo pipefail

dep_dir="$PWD/dep"
super_dir="$PWD/super"
clone_dir="$PWD/git-clone"

git init -b main "$dep_dir" >/dev/null
git -C "$dep_dir" config user.email test@pitmaster.dev
git -C "$dep_dir" config user.name "Test User"
printf 'dependency\n' > "$dep_dir/dep.txt"
git -C "$dep_dir" add dep.txt
git -C "$dep_dir" commit -m dep >/dev/null

git init -b main "$super_dir" >/dev/null
git -C "$super_dir" config user.email test@pitmaster.dev
git -C "$super_dir" config user.name "Test User"
git -C "$super_dir" -c protocol.file.allow=always submodule add "$dep_dir" vendor/lib >/dev/null
git -C "$super_dir" commit -am 'Add submodule' >/dev/null
git clone "$super_dir" "$clone_dir" >/dev/null
git -C "$clone_dir" -c protocol.file.allow=always submodule update --init >/dev/null

git -C "$clone_dir" submodule status --cached | sed -E 's/^[ +-]?[0-9a-f]{40} //' > .submodule-status.txt
git -C "$clone_dir/vendor/lib" show HEAD:dep.txt > .submodule-head.txt
git -C "$clone_dir/vendor/lib" branch --show-current > .submodule-branch.txt
