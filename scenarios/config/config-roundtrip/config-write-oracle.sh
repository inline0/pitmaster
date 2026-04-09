#!/usr/bin/env bash
set -euo pipefail

scenario_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

rm -f oracle.config
git config --file oracle.config core.filemode false
git config --file oracle.config core.logAllRefUpdates true
git config --file oracle.config alias.lg "log --oneline"
git config --file oracle.config remote.origin.url https://example.com/repo.git
git config --file oracle.config --add remote.origin.fetch +refs/heads/*:refs/remotes/origin/*
git config --file oracle.config --add remote.origin.fetch ^refs/heads/tmp
git config --file oracle.config branch.main.merge refs/heads/main

bash "$scenario_dir/config-read.sh" oracle.config
