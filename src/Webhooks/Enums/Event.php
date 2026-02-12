<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Enums;

enum Event: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case React = 'react';
}
