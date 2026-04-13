#!/usr/bin/env bash
set -euo pipefail

script_dir=$(cd "$(dirname "$0")" && pwd)

php "$script_dir/actual.php"
php "$script_dir/config-read.php" rewritten.config
