<?php

namespace App\Events;

use App\Models\ApplicationMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationMessagePosted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApplicationMessage $message
    ) {}
}
