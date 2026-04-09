#!/usr/bin/env bash
set -euo pipefail

git sparse-checkout init --cone >/dev/null
git sparse-checkout set src docs >/dev/null
cat .git/info/sparse-checkout > .sparse-file.txt
php <<'PHP'
<?php
require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Config\GitConfig;

file_put_contents(
    '.sparse-config-worktree.json',
    json_encode(GitConfig::fromFile('.git/config.worktree')->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
PHP
