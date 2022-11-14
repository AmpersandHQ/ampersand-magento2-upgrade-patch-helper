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
    public const TYPE_DB_SCHEMA_ADDED = 'DB schema added';
    public const TYPE_DB_SCHEMA_CHANGED = 'DB schema changed';
    public const TYPE_DB_SCHEMA_REMOVED = 'DB schema removed';
    public const TYPE_DB_SCHEMA_TARGET_CHANGED = 'DB schema target changed';

    /**
     * @var string[]
     */
    public static $dbSchemaTypes = [
        self::TYPE_DB_SCHEMA_ADDED,
        self::TYPE_DB_SCHEMA_CHANGED,
        self::TYPE_DB_SCHEMA_REMOVED,
        self::TYPE_DB_SCHEMA_TARGET_CHANGED
    ];
}
