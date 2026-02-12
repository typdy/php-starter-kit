<?php

declare(strict_types=1);

namespace Typdy\StarterKit\Webhooks\Enums;

enum Domain: string
{
    case Blueprints = 'blueprints';
    case Collections = 'collections';
    case Constructs = 'constructs';
    case Globals = 'globals';
    case Fields = 'fields';
    case Languages = 'languages';
    case Permissions = 'permissions';
}
