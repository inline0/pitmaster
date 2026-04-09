#!/usr/bin/env bash
set -euo pipefail

if git checkout main >/dev/null 2>&1; then
    printf 'allowed\n' > .checkout-result.txt
    exit 1
fi

printf 'blocked\n' > .checkout-result.txt
