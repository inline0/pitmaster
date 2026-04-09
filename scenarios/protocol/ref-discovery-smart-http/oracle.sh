#!/usr/bin/env bash
set -euo pipefail

url="$(cat .remote-url)"
git ls-remote --symref "$url" > .ls-remote.txt

php -r '
require getenv("PITMASTER_ROOT") . "/vendor/autoload.php";
use Pitmaster\Protocol\Capability;
use Pitmaster\Protocol\PktLine;
use Pitmaster\Protocol\RefDiscovery;
use Pitmaster\Object\ObjectId;

$url = trim((string) file_get_contents(".remote-url"));
$response = file_get_contents($url . "/info/refs?service=git-upload-pack");
if ($response === false) {
    fwrite(STDERR, "failed to fetch info/refs\n");
    exit(1);
}

$capabilities = null;
$refs = [];
$headSymref = null;

foreach (PktLine::decode($response) as $line) {
    if ($line === null || !is_string($line) || str_starts_with($line, "# service=")) {
        continue;
    }

    if (str_contains($line, "\0")) {
        [$refPart, $capPart] = explode("\0", $line, 2);
        $line = $refPart;
        $capabilities = Capability::parse($capPart);
        $symref = $capabilities->get("symref");
        if ($symref !== null && str_starts_with($symref, "HEAD:")) {
            $headSymref = substr($symref, 5);
        }
    }

    $parts = explode(" ", $line, 2);
    if (count($parts) === 2 && strlen($parts[0]) === 40 && ctype_xdigit($parts[0])) {
        $refs[$parts[1]] = ObjectId::fromHex($parts[0]);
    }
}

$discovery = RefDiscovery::fromParsed($refs, $capabilities, $headSymref);
$lines = ["head=" . ($discovery->headSymref() ?? "<detached>")];
$capabilities = $discovery->capabilities()?->all() ?? [];
ksort($capabilities);
foreach ($capabilities as $name => $value) {
    if ($name === "agent" && $value !== null) {
        $value = "<normalized>";
    }
    $lines[] = "cap " . $name . ($value !== null ? "=" . $value : "");
}
$refs = $discovery->refs();
ksort($refs);
foreach ($refs as $name => $id) {
    $lines[] = "ref {$name}={$id->hex}";
}
file_put_contents(".discovery-state", implode("\n", $lines) . "\n");
'
