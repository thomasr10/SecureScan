<?php

namespace App\Enum;

enum ProjectStatus: string
{
    case PROCESSING = 'processing';
    case DONE = 'done';
    case ERROR = 'error';
}