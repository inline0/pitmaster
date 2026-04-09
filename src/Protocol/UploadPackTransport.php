<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

interface UploadPackTransport
{
    public function discoverRefs(string $url): RefDiscovery;

    public function uploadPack(string $url, string $body): string;
}
