<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

enum RegexDelimiterPosition
{
    case None;
    case Prefix;
    case Suffix;
}
