#!/usr/bin/env bash
set -euo pipefail

scenario_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bash "$scenario_dir/config-read.sh" written.config
