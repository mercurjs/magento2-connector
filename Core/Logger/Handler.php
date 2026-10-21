<?php

declare(strict_types=1);

namespace Mercur\Core\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;

class Handler extends Base
{
    protected $fileName = '/var/log/mercur.log';

    protected $loggerType = MonologLogger::DEBUG;
}
