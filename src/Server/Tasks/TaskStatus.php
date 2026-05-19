<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tasks;

class TaskStatus
{
    public const WORKING = 'working';

    public const INPUT_REQUIRED = 'input_required';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';
}
