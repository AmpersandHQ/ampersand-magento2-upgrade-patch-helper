<?php

namespace Ampersand\PatchHelper;

class Checks
{
    public const TYPE_FILE_OVERRIDE = 'Override (phtml/js/html)';
    public const TYPE_QUEUE_CONSUMER_ADDED = 'Queue consumer added';
    public const TYPE_QUEUE_CONSUMER_REMOVED = 'Queue consumer removed';
    public const TYPE_QUEUE_CONSUMER_CHANGED = 'Queue consumer changed';
    public const TYPE_PREFERENCE = 'Preference';
    public const TYPE_METHOD_PLUGIN = 'Plugin';
}
