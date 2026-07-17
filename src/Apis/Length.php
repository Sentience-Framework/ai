<?php

namespace Sentience\Ai\Apis;

enum Length: int
{
    case ExtraSmall = 1024;
    case Small = 4096;
    case Medium = 8192;
    case Large = 16384;
    case ExtraLarge = 65536;
}
