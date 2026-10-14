---
title: "Clone, Fetch, Push"
description: "Network operations for cloning, fetching, and pushing repositories."
path: "network/clone-fetch-push"
order: 130
section: "Network"
meta_title: "Clone, Fetch, Push"
meta_description: "Network operations for cloning, fetching, and pushing repositories."
---

# Clone, Fetch, Push

Pitmaster supports the three fundamental git network operations: clone (initial download), fetch (incremental download), and push (upload). SSH URLs (`ssh://...`, `user@host:path`) use the SSH transport; other URLs use smart HTTP, and clone and fetch fall back to dumb HTTP when the smart exchange fails (see [Protocols](/docs/network/protocols)).

All three start network transports, so they throw `RuntimeException` on a handle opened with `['processes' => false]` or `Pitmaster::processFreeOptions()`.

## Clone

```php
use Pitmaster\Pitmaster;

// public static function clone(string $url, string $path, ?int $depth = null, array $options = []): Repository
$repo = Pitmaster::clone('https://github.com/user/repo.git', '/path/to/destination');
$repo = Pitmaster::clone('https://github.com/user/repo.git', '/path/to/shallow', 1);
```

The new repository always uses SHA-1. If the clone fails and the destination did not exist before, the partly created directory is removed.

### Clone flow

1. **Initialize**: create a new empty repository at the destination path
2. **Configure remote**: save the URL as `remote.origin.url` and set up the fetch refspec
3. **Discover refs**: `GET /info/refs?service=git-upload-pack` to learn what the server has
4. **Negotiate**: send `want` lines for all remote refs (deduplicated by hash)
5. **Receive pack**: download the pack file containing all requested objects
6. **Write pack**: save the pack file to `.git/objects/pack/`
7. **Index pack**: generate the `.idx` file for fast lookups
8. **Set up refs**: create remote tracking branches (`refs/remotes/origin/*`) and tags
9. **Set HEAD**: point HEAD to the default branch (from the server's `symref=HEAD:...` capability, or `refs/heads/main` when the server sends none), and set `branch.<name>.remote` and `branch.<name>.merge`
10. **Checkout**: check out that branch, which writes the working tree and the index

Errors during the final checkout are caught and ignored, so a clone can return with an empty or partial working tree ([#19](https://github.com/inline0/pitmaster/issues/19)).

```php
$repo = Pitmaster::clone($url, $path);
$head = $repo->head();
echo $head->message;

$branches = $repo->branches();
$files = $repo->status();
```

### Working tree materialization

After receiving the pack, Pitmaster calls `$repo->checkout()` for the default branch. That writes every file of the HEAD commit's tree to the working directory and builds the index from the tree entries, so `status()` reports a clean working tree.

## Fetch

```php
// public function fetch(string $remote = 'origin', ?int $depth = null): void
$repo->fetch();              // Fetch from 'origin'
$repo->fetch('upstream');    // Fetch from a named remote
$repo->fetch('origin', 1);   // Shallow fetch
```

### Fetch flow

1. **Read remote URL**: from `.git/config` (`remote.<name>.url`)
2. **Discover refs**: get the current state of all remote refs
3. **Negotiate wants/haves**:
   - Want: remote refs matched by the `remote.<name>.fetch` refspecs (default `+refs/heads/*:refs/remotes/<name>/*`) whose objects are missing locally (all of them when a depth is given)
   - Have: every local ref tip
4. **Receive pack**: download only the new objects
5. **Write pack**: save to `.git/objects/pack/` and build its index
6. **Update tracking refs**: update `refs/remotes/<remote>/<branch>` for each matched remote branch, and write every advertised tag ref. Tag objects are not requested separately, so a new annotated tag can be written as a ref to an object that was not downloaded ([#24](https://github.com/inline0/pitmaster/issues/24))

```php
// Fetch is incremental: only downloads what's new
$repo->fetch();

// After fetching, merge or rebase to incorporate changes (use the full ref name)
$result = $repo->merge('refs/remotes/origin/main');
```

`resolve()` looks names up as `refs/heads/<name>`, `refs/tags/<name>`, and the name itself, so the short form `origin/main` is not found.

Pitmaster requests thin packs, which it cannot index yet ([#16](https://github.com/inline0/pitmaster/issues/16)). A server that answers an incremental fetch with a thin pack makes `fetch()` throw `PackParseException`.

### Already up to date

If every object the remote refs point to already exists locally, no pack is requested and `fetch()` returns after ref discovery. In that case the tracking refs are not updated either, even if the remote branches moved to commits you already have ([#23](https://github.com/inline0/pitmaster/issues/23)).

## Push

```php
$repo->push();                        // Push current branch to 'origin'
$repo->push('origin', 'main');        // Push a specific branch
$repo->push('upstream', 'feature');   // Push to a named remote
```

### Push flow

1. **Read remote URL**: from `.git/config`
2. **Determine what to push**: resolve the local branch to its commit hash
3. **Discover remote refs**: get the current state of the remote branch (to compute the old hash)
4. **Check fast-forward**: if the remote branch is not an ancestor of the local branch, throw `RuntimeException("Push rejected: non-fast-forward update to <branch>")` before sending anything
5. **Send ref update**: `<old-hash> <new-hash> refs/heads/<branch>`
6. **Send pack data**: pack containing the objects the remote needs
7. **Check result**: server responds with success/failure per ref

The `pre-push` hook runs before the update is sent, unless hooks are disabled.

```php
// Push the current branch
$repo->push();

// Push a specific branch to a specific remote
$repo->push('origin', 'feature/login');
```

### Push requirements

- The current branch must exist locally
- A remote must be configured with a URL
- The remote must accept the push (authentication, permissions, non-fast-forward policies)

### Force push

Pitmaster sends the old hash from the remote's live ref advertisement, and `push()` refuses non-fast-forward updates locally. To overwrite a remote branch, use force-with-lease:

```php
// Lease defaults to refs/remotes/<remote>/<branch>; pass an ObjectId to set it explicitly
$repo->pushForceWithLease('origin', 'main');
$repo->pushForceWithLease('origin', 'main', $repo->resolve('refs/remotes/origin/main'));

// Update several branches in one atomic request
$repo->pushAtomic('origin', ['main', 'release']);

// Make the remote mirror local refs (also deletes remote refs that no longer exist locally)
$repo->pushMirror('origin');
```

## UploadPackClient (fetch internals)

```php
use Pitmaster\Protocol\UploadPackClient;
use Pitmaster\Protocol\SmartHttpClient;

$http = new SmartHttpClient();
$uploadPack = new UploadPackClient($http);

// Fetch with explicit wants and haves
$packData = $uploadPack->fetch($url, $wants, $haves);
```

The want/have negotiation builds a pkt-line request:

```
want <hash1> <capabilities>\n
want <hash2>\n
deepen <depth>\n        (only when a depth is given)
0000
have <hash3>\n
have <hash4>\n
done\n
```

The server responds with acknowledgments and then the pack data (possibly side-band wrapped).

## ReceivePackClient (push internals)

```php
use Pitmaster\Protocol\ReceivePackClient;
use Pitmaster\Protocol\SmartHttpClient;

$http = new SmartHttpClient();
$receivePack = new ReceivePackClient($http);

$receivePack->push($url, [
    [
        'old' => $oldId,      // Current remote hash (or zero hash for new branch)
        'new' => $localId,    // Local commit hash to push
        'ref' => 'refs/heads/main',
    ],
], $packData);
```

The push request format:

```
<old-hash> <new-hash> refs/heads/main\0<capabilities>\n
0000
PACK<pack data>
```

## Error handling

```php
use Pitmaster\Exceptions\ProtocolException;

try {
    $repo->fetch();
} catch (ProtocolException $e) {
    // Network unreachable, auth failed, server error, etc.
    echo $e->getMessage();
}

try {
    $repo->push();
} catch (\RuntimeException $e) {
    // Remote not configured, not on a branch, branch not found, non-fast-forward
    echo $e->getMessage();
}
```

`fetch()` can also throw a plain `RuntimeException` (unknown remote, process-free handle) or `PackParseException` (a pack that cannot be indexed). `ProtocolException` and `PackParseException` both extend `\RuntimeException`, so catching `\RuntimeException` covers all of them.

## Remote configuration

Remotes are stored in `.git/config`:

```ini
[remote "origin"]
    url = https://github.com/user/repo.git
    fetch = +refs/heads/*:refs/remotes/origin/*
```

Access via the config API:

```php
$config = $repo->config();
$url = $config->get('remote.origin.url');
$config->set('remote.upstream.url', 'https://github.com/other/repo.git');
$config->set('remote.upstream.fetch', '+refs/heads/*:refs/remotes/upstream/*');

// set() changes the in-memory config only; write it to persist the change
$config->writeToFile($repo->commonGitDir() . '/config');
```

## Pack data for push

Currently, Pitmaster collects the objects reachable from the commits being pushed that are not reachable from the remote's current refs, and writes them as base objects (no delta compression) into a pack. This is functionally correct but produces larger packs than `git push`. Delta compression in pack writing is a planned optimization.
