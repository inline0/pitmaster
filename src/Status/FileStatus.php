<?php

declare(strict_types=1);

namespace Pitmaster\Status;

enum FileStatus: string
{
    case Added = 'A';
    case Modified = 'M';
    case Deleted = 'D';
    case Renamed = 'R';
    case Copied = 'C';
    case Untracked = '?';
    case Ignored = '!';
    case Unmodified = ' ';
}
