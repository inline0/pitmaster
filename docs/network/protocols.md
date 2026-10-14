---
title: "Protocols"
description: "All supported network protocols for git communication."
path: "network/protocols"
order: 120
section: "Network"
meta_title: "Protocols"
meta_description: "All supported network protocols for git communication."
---

# Protocols

Pitmaster supports multiple network transport protocols for communicating with git servers. Smart HTTP is the primary transport. Additional protocols are available for broader compatibility.

## Smart HTTP (primary)

The recommended transport. Works over HTTPS and is the most widely deployed protocol. There is no API for custom headers or credentials; credentials can only be passed in the URL (`https://user:token@host/repo.git`), which PHP sends as Basic authentication. `clone()` stores the URL, credentials included, as `remote.origin.url` in `.git/config`. Requests use PHP's stream wrappers, so `allow_url_fopen` must be enabled.

```php
use Pitmaster\Protocol\SmartHttpClient;

$http = new SmartHttpClient(timeout: 30);
$discovery = $http->discoverRefs('https://github.com/user/repo.git');
```

See [Smart HTTP](/docs/network/smart-http) for full details.

### Protocol v1

The traditional smart HTTP protocol. All refs are sent during discovery, and negotiation uses want/have/done lines.

```php
use Pitmaster\Protocol\ProtocolV1;
```

### Protocol v2

The newer protocol with structured commands (`ls-refs`, `fetch`). More efficient for repositories with many refs because the client can filter the ref list.

`clone()` and `fetch()` always use protocol v1. Protocol v2 is available explicitly over smart HTTP through `SmartHttpClient::discoverRefsV2()` and `UploadPackClient::fetchV2()`.

## Dumb HTTP

Fallback for servers that do not support the smart HTTP protocol. The dumb protocol fetches objects and packs individually via plain HTTP GET requests.

```php
use Pitmaster\Protocol\DumbHttpClient;

$client = new DumbHttpClient();
```

Dumb HTTP limitations:
- No negotiation (client must download everything)
- No delta compression during transfer
- Significantly slower than smart HTTP
- Some servers (GitHub, GitLab) do not support it

The dumb protocol fetches:
1. `GET /info/refs` (plain text, no pkt-line)
2. `GET /HEAD` (default branch)
3. `GET /objects/info/packs` (list of available packs)
4. `GET /objects/pack/pack-<hash>.pack` and `.idx` (each listed pack and its index)
5. `GET /objects/<xx>/<rest>` (individual loose objects, walked from the ref tips)

After importing listed packs, the walker visits ref tips and downloads any missing loose objects. Packed and newer loose commits can coexist on the remote. The dumb client accepts 40-character (SHA-1) object IDs only. `clone()` and `fetch()` fall back to dumb HTTP when the smart HTTP exchange fails.

## SSH

SSH transport uses the `ssh2` PHP extension when it is loaded. Otherwise it starts the system `ssh` client through `proc_open()`. Either way it runs `git-upload-pack` or `git-receive-pack` on the remote.

```php
use Pitmaster\Protocol\SshClient;

$client = new SshClient();
```

SSH URLs:
```
git@github.com:user/repo.git
ssh://git@github.com/user/repo.git
```

No extension is required. The two paths behave differently:

**System `ssh` client** (used when `ssh2` is not loaded, or when the selected settings require OpenSSH). Pitmaster runs `ssh -o BatchMode=yes -o ConnectTimeout=30 -p <port> <user>@<host> <command>`, so authentication and host key checking follow your SSH configuration and agent. These environment variables adjust the command:

| Variable | Effect |
|----------|--------|
| `PITMASTER_SSH_COMMAND` | Executable to run instead of `ssh` |
| `PITMASTER_SSH_IDENTITY_FILE` | Passed as `-i <file>` |
| `PITMASTER_SSH_KNOWN_HOSTS` | Passed as `-o UserKnownHostsFile=<file>` |
| `PITMASTER_SSH_STRICT_HOST_KEY_CHECKING` | Passed as `-o StrictHostKeyChecking=<value>` |

This path starts a process, so it is not available on handles opened with `['processes' => false]`.

**`ssh2` extension**. Before authentication, Pitmaster verifies the negotiated host-key fingerprint against `PITMASTER_SSH_KNOWN_HOSTS` (or `~/.ssh/known_hosts`) and `/etc/ssh/ssh_known_hosts`. Hashed hosts, non-default ports, wildcard/negated host lists, and revoked keys are checked. An unknown or changed key fails. The extension exposes SHA-1 fingerprints, which are compared with the known public-key bytes.

Authentication first tries `PITMASTER_SSH_IDENTITY_FILE` with its `.pub` file, or both default RSA and Ed25519 key pairs, then the agent. An explicit SSH command, an identity without a companion `.pub`, or strict-checking modes such as `accept-new` and `no` select the system client so OpenSSH applies that setting. Paths with spaces are supported.

The SSH transport runs `git-upload-pack` or `git-receive-pack` as a remote command and communicates over the SSH channel using the same pkt-line protocol as smart HTTP.

## Git protocol (TCP)

The native git protocol communicates over TCP port 9418 using PHP sockets. `GitProtocolClient` implements ref discovery and `upload-pack` (fetching); it cannot push. `clone()` and `fetch()` do not route `git://` URLs to it, so use the client directly.

```php
use Pitmaster\Protocol\GitProtocolClient;

$client = new GitProtocolClient();
```

Git URLs:
```
git://github.com/user/repo.git
```

Characteristics:
- No authentication (read-only in practice)
- No encryption
- Deprecated by most hosting providers
- Uses the same pkt-line framing as smart HTTP

The git protocol sends the service name and repository path as the initial request, then proceeds with standard pkt-line ref discovery and pack negotiation.

## Bundles

Git bundles are offline pack files with ref information. They allow transferring repository data without a network connection.

```php
use Pitmaster\Protocol\Bundle;

// Read a bundle file
$bundle = Bundle::open('/path/to/repo.bundle');
$refs = $bundle->refs();
$packData = $bundle->packData();
$prerequisites = $bundle->prerequisites();

// Create one
$bundle = Bundle::create(['refs/heads/main' => $commitId], $packData);
$bundle->writeTo('/path/to/out.bundle');
```

Pitmaster reads v2 and v3 bundle headers. There is no `Repository` method to import a bundle: write `packData()` to `.git/objects/pack/`, build its index with `PackIndexer::writeIndex()`, and update the refs yourself.

Bundle format:
```
# v2 git bundle\n
-<prerequisite hash>\n    (zero or more)
<hash> <refname>\n        (one or more)
\n
<pack data>               (standard pack file format)
```

Bundles are useful for:
- Sneakernet transfers (USB drives)
- Incremental backups
- Seeding clones in air-gapped environments

## Shallow clones

Pitmaster supports shallow clones, where only a limited depth of commit history is fetched.

```php
use Pitmaster\Protocol\ShallowClone;
```

Pass a depth to `Pitmaster::clone($url, $path, $depth)` or `$repo->fetch($remote, $depth)`. The request adds the `shallow` capability and a `deepen` line:
```
want <hash>
deepen <depth>
```

The server responds with `shallow <hash>` lines indicating where the history was truncated. Pitmaster computes the shallow boundary from the depth itself and uses the server's `shallow` lines when that computation finds nothing; the result is stored in `.git/shallow`. `ShallowClone::readShallow()`, `writeShallow()`, and `isShallow()` work with that file directly.

## Protocol comparison

| Protocol | Auth | Encryption | Push | Typical use |
|----------|------|-----------|------|-------------|
| Smart HTTP | Credentials in the URL | HTTPS | Yes | Primary transport |
| Dumb HTTP | Credentials in the URL | HTTPS | No | Legacy fallback |
| SSH | SSH keys or agent | SSH | Yes | Private repos, CI |
| Git (TCP) | None | None | No | Read-only mirrors |
| Bundle | N/A | N/A | N/A | Offline transfer |

## Transport selection

`Pitmaster::clone()`, `$repo->fetch()`, and `$repo->push()` select the transport from the URL:

| URL pattern | Transport |
|------------|-----------|
| `ssh://...` | SSH |
| `user@host:path` | SSH |
| Anything else (`https://...`, `http://...`) | Smart HTTP; clone and fetch fall back to dumb HTTP |

`git://` URLs and local paths are not supported by these methods: they are sent to the smart HTTP client and fail. Use `GitProtocolClient` directly for `git://`. URLs with a scheme use that scheme to select the transport. HTTPS URLs with credentials and ports remain HTTP remotes.
