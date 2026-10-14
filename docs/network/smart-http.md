---
title: "Smart HTTP"
description: "Git smart HTTP transport, PktLine encoding, ref discovery, capabilities, and side-band."
path: "network/smart-http"
order: 110
section: "Network"
meta_title: "Smart HTTP"
meta_description: "Git smart HTTP transport, PktLine encoding, ref discovery, capabilities, and side-band."
---

# Smart HTTP

Pitmaster communicates with git servers over HTTPS using the smart HTTP protocol. This is the primary network transport. It uses PHP's native stream functions (`fopen` with a stream context) for HTTP requests, so `allow_url_fopen` must be enabled.

## SmartHttpClient

```php
use Pitmaster\Protocol\SmartHttpClient;

$http = new SmartHttpClient();
// Or with custom timeout:
$http = new SmartHttpClient(timeout: 60);
```

The timeout defaults to the `PITMASTER_HTTP_TIMEOUT` constant (an integer), or 30 seconds when it is not defined.

### Discover refs

```php
$discovery = $http->discoverRefs('https://github.com/user/repo.git');

foreach ($discovery->refs() as $name => $id) {
    echo "{$id->hex} {$name}\n";
}

// HEAD symref (e.g., 'refs/heads/main')
$defaultBranch = $discovery->headSymref();

// Specific ref
$mainId = $discovery->ref('refs/heads/main');

// Server capabilities (a Capability object, or null)
$caps = $discovery->capabilities();
```

`discoverReceivePackRefs()` does the same for `git-receive-pack`. The ref discovery request goes to:
```
GET <url>/info/refs?service=git-upload-pack
```

The response is a pkt-line encoded list of refs with capabilities on the first line.

### Upload pack (fetch)

```php
$response = $http->uploadPack($url, $requestBody);
```

Sends a POST to `<url>/git-upload-pack` with the want/have negotiation body. Returns the raw response body (pkt-lines, `NAK`, and side-band data). `UploadPackClient` builds the request and extracts the pack:

```php
use Pitmaster\Protocol\UploadPackClient;

$client = new UploadPackClient($http);
$packData = $client->fetch($url, $wants, $haves);   // ObjectId[] wants and haves
```

### Receive pack (push)

```php
$response = $http->receivePack($url, $requestBody);
```

Sends a POST to `<url>/git-receive-pack` with the ref update commands and pack data.

## PktLine encoding

The git protocol uses pkt-line framing for all communication. Each line is prefixed with its total length (including the 4-byte length prefix) encoded as 4 hex digits.

```php
use Pitmaster\Protocol\PktLine;

// Encode a single line
$encoded = PktLine::encode("want abc123def456\n");
// "0016want abc123def456\n"

// Flush packet (end of section)
$flush = PktLine::flush();
// "0000"

// Delimiter packet (protocol v2)
$delim = PktLine::delimiter();
// "0001"

// Decode a stream
$lines = PktLine::decode($rawData);
// Each element is: string (data), null (flush), or false (delimiter)
```

### Length rules

- Minimum data line: `0005` (1 byte payload)
- Maximum data line: `fff0` (65516 bytes payload)
- Flush packet: `0000`
- Delimiter packet: `0001`
- Response-end: `0002` (protocol v2; handled by `ProtocolV2::decode()`, while `PktLine::decode()` rejects it)

```php
PktLine::MAX_PAYLOAD;  // 65516 (65520 - 4)
```

## Ref discovery

The `RefDiscovery` class parses the server's ref advertisement.

```php
use Pitmaster\Protocol\RefDiscovery;

$discovery = RefDiscovery::parse($pktLines);

// All refs
$refs = $discovery->refs();
// ['refs/heads/main' => ObjectId, 'refs/tags/v1.0' => ObjectId, ...]

// Single ref
$id = $discovery->ref('refs/heads/main');

// HEAD symbolic ref (from capabilities)
$symref = $discovery->headSymref();
// 'refs/heads/main' or null

// Server capabilities
$caps = $discovery->capabilities();
// Capability object (see below), or null
```

### Ref advertisement format

The first line of the advertisement includes capabilities:

```
<hash> HEAD\0<capability list>\n
<hash> refs/heads/main\n
<hash> refs/tags/v1.0\n
0000
```

## Capabilities

The `Capability` class parses and manages protocol capabilities.

```php
use Pitmaster\Protocol\Capability;

$caps = Capability::parse('thin-pack side-band-64k ofs-delta symref=HEAD:refs/heads/main');

$caps->has('thin-pack');           // true
$caps->get('symref');              // 'HEAD:refs/heads/main'
$caps->all();                      // ['thin-pack' => null, ..., 'symref' => 'HEAD:refs/heads/main']
```

### Common capabilities

| Capability | Meaning |
|-----------|---------|
| `multi_ack` | Server supports multi-ack negotiation |
| `thin-pack` | Server can omit delta bases. Pitmaster does not request this capability; received packs remain self-contained |
| `side-band` | Server uses side-band for pack data (limited to 1000 bytes) |
| `side-band-64k` | Server uses 64K side-band for pack data |
| `ofs-delta` | Pack may use OFS_DELTA objects |
| `shallow` | Server supports shallow clone |
| `no-done` | Server does not require a "done" line |
| `symref=HEAD:refs/heads/main` | HEAD's symbolic target |
| `agent=git/2.45.0` | Server git version |

## Side-band demultiplexing

When the server uses side-band, the pack data is multiplexed with progress and error messages. The first byte of each pkt-line payload indicates the channel:

| Channel | Meaning |
|---------|---------|
| 1 | Pack data |
| 2 | Progress messages (stderr) |
| 3 | Fatal error messages |

Pitmaster's `UploadPackClient` strips the channel bytes and reassembles the pack data.

## Protocol versions

### v1 (default)

The traditional protocol. Ref discovery sends all refs at once. Pitmaster negotiates in a single round: it sends its wants, every local ref tip as a `have`, and `done`.

### v2

A simpler protocol with better performance.

Key differences from v1:
- Ref discovery is a separate `ls-refs` command (can filter refs)
- Fetch is a single `fetch` command with structured parameters
- Uses delimiter packets (`0001`) to separate sections

`clone()` and `fetch()` always use v1. v2 is available when you call it explicitly:

```php
$discovery = $http->discoverRefsV2($url);       // ls-refs
$packData = (new UploadPackClient($http))->fetchV2($url, $wants, $haves);
```

## HTTP request flow

### Clone/Fetch

```
1. GET  /info/refs?service=git-upload-pack
   Response: pkt-line encoded ref list

2. POST /git-upload-pack
   Request:  want <hash>\n ... have <hash>\n ... done\n
   Response: NAK\n + pack data (possibly side-band wrapped)
```

### Push

```
1. GET  /info/refs?service=git-receive-pack
   Response: pkt-line encoded ref list

2. POST /git-receive-pack
   Request:  <old-hash> <new-hash> refs/heads/main\0<capabilities>\n
             0000
             PACK<pack data>
   Response: unpack ok\n + ref status lines
```

## Error handling

Network errors throw `ProtocolException`. When the smart HTTP exchange of `clone()` or `fetch()` fails, Pitmaster retries over dumb HTTP and rethrows the original error if that fails too. Other failures (an unknown remote, a process-free handle, a pack that cannot be indexed) throw other exception types:

```php
use Pitmaster\Exceptions\ProtocolException;

try {
    $repo->fetch();
} catch (ProtocolException $e) {
    echo "Network error: {$e->getMessage()}\n";
}
```

Common errors:
- Server unreachable (timeout)
- Authentication required or repository not found (reported as `Unexpected HTTP status 401`, `403`, or `404`)
- Unexpected `Content-Type` in the response
- Invalid pkt-line data
- Side-band error channel message
