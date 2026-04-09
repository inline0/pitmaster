#!/usr/bin/env bash
set -euo pipefail

url="$(cat .remote-url)"
trace=".git-trace-receive-pack"

git clone "$url" git-clone >/dev/null 2>&1
(
    cd git-clone
    git config user.email test@pitmaster.dev
    git config user.name "Test User"
    cat > git-push.txt <<'EOF'
git push
EOF
    git add git-push.txt
    git commit -m git-push >/dev/null
    GIT_TRACE_PACKET=1 git -c protocol.version=1 push origin main >/dev/null 2>"../$trace"
)

php -r '
$trace = file($argv[1], FILE_IGNORE_NEW_LINES) ?: [];
$lines = [];
foreach (array_reverse($trace) as $line) {
    $needle = "git>";
    $pos = strpos($line, $needle);
    if ($pos === false) {
        continue;
    }
    $payload = trim(substr($line, $pos + strlen($needle)));
    if (preg_match("/^[0-9a-f]{40} [0-9a-f]{40} refs\\//", $payload) === 1) {
        $payload = preg_replace(
            "/^[0-9a-f]{40} [0-9a-f]{40}/",
            "<old> <new>",
            $payload,
        ) ?? $payload;
        $lines[] = preg_replace("/agent=[^ ]+/", "agent=<normalized>", $payload) ?? $payload;
        $lines[] = "0000";
        break;
    }
}
file_put_contents($argv[2], implode("\n", $lines) . "\n");
' "$trace" .receive-pack-lines
