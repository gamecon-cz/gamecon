<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class EnumParameterDriver extends AbstractDriverMiddleware
{
    public function connect(
        #[\SensitiveParameter]
        array $params,
    ) {
        return new EnumParameterConnection(parent::connect($params));
    }
}
