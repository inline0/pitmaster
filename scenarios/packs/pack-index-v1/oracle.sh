#!/usr/bin/env bash
set -euo pipefail

fixture="$PITMASTER_ROOT/fixtures/upstream/dulwich/testdata/packs/pack-bc63ddad95e7321ee734ea11a7a62d314e0d7481.idx"
git show-index < "$fixture" | awk '{print $2}' > .pack-state
