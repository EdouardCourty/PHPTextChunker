<?php

declare(strict_types=1);

namespace Ecourty\PlatformParameterBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class TemplateBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
