<?php

declare(strict_types=1);

namespace Gamecon\Cache;

enum ProgramStaticFileType: string
{
    case AKTIVITY = 'aktivity';
    case POPISY = 'popisy';
    case OBSAZENOSTI = 'obsazenosti';
}
