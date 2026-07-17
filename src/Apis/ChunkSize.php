<?php

namespace Sentience\Ai\Apis;

enum ChunkSize: int
{
    case XS = 1024;
    case S = 4096;
    case M = 8192;
    case L = 16384;
    case XL = 65536;
}
