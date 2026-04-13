#!/usr/bin/env bash
set -euo pipefail

script_dir=$(cd "$(dirname "$0")" && pwd)

rm -f rewritten.config
git config --file rewritten.config alias.keep "status -sb"
git config --file rewritten.config remote.origin.url https://example.com/repo.git
git config --file rewritten.config --add remote.origin.fetch +refs/heads/*:refs/remotes/origin/*
git config --file rewritten.config --add remote.origin.fetch ^refs/heads/tmp
git config --file rewritten.config --add remote.origin.fetch refs/tags/*:refs/tags/*

bash "$script_dir/config-read.sh" rewritten.config
