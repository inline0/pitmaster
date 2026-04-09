<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Index\Index;
use Pitmaster\Index\IndexWriter;

$indexPath = getcwd() . '/.git/index';
$index = Index::open($indexPath);
IndexWriter::write($index, $indexPath);
