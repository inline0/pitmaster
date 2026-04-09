<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

interface ReceivePackTransport
{
    public function discoverReceivePackRefs(string $url): RefDiscovery;

    public function receivePack(string $url, string $body): string;
}
