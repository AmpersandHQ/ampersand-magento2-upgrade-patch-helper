<?php

namespace Ampersand\PatchHelper\Checks;

use Ampersand\PatchHelper\Checks;

class DbSchemaXml extends AbstractCheck
{
    /**
     * @return bool
     */
    public function canCheck()
    {
        return str_ends_with($this->patchEntry->getPath(), '/etc/db_schema.xml');
    }

    /**
     * Check if db schema has changed/removed/added a table definition
     *
     * @return void
     */
    public function check()
    {
        $vendorFile = $this->patchEntry->getPath();

        try {
            $originalDefinitions = $this->patchEntry->getDatabaseTablesDefinitionsFromOriginalFile();
            $newDefinitions = $this->patchEntry->getDatabaseTablesDefinitionsFromNewFile();

            foreach ($originalDefinitions as $tableName => $definition) {
                if (!isset($newDefinitions[$tableName])) {
                    $this->infos[Checks::TYPE_DB_SCHEMA_REMOVED][$tableName] = $tableName;
                }
            }
            unset($tableName, $definition);

            foreach ($newDefinitions as $tableName => $definition) {
                if (!isset($originalDefinitions[$tableName])) {
                    $this->infos[Checks::TYPE_DB_SCHEMA_ADDED][$tableName] = $tableName;
                }
            }
            unset($tableName, $definition);

            foreach ($newDefinitions as $tableName => $newDefinition) {
                if (!(isset($originalDefinitions[$tableName]) && is_array($newDefinition))) {
                    continue; // This table is not defined in the original and new definitions
                }
                if ($originalDefinitions[$tableName]['amp_upgrade_hash'] === $newDefinition['amp_upgrade_hash']) {
                    continue; // The hash for this table
                }
                $this->infos[Checks::TYPE_DB_SCHEMA_CHANGED][$tableName] = $tableName;
            }
            unset($tableName, $newDefinition);

            if (
                empty($this->infos[Checks::TYPE_DB_SCHEMA_CHANGED]) &&
                empty($this->infos[Checks::TYPE_DB_SCHEMA_ADDED]) &&
                empty($this->infos[Checks::TYPE_DB_SCHEMA_REMOVED])
            ) {
                throw new \InvalidArgumentException("$vendorFile could not work out db schema changes for this diff");
            }

            /*
             * Promote INFO to WARNING in the case that we are modifying a table defined by another db_schema.xml
             *
             * This is identified by looking for the primary key definition of a table, if there's only one we can be
             * certain that we're modifying a table defined elsewhere
             *
             * This ignores magento<->magento modifications, all things are still reported as INFO level otherwise
             */
            $primaryTableToFile = $this->m2->getDbSchemaPrimaryDefinition();
            $primaryDefinitionsInThisFile = [];
            foreach (Checks::$dbSchemaTypes as $dbSchemaType) {
                if (!(isset($this->infos[$dbSchemaType]) && !empty($this->infos[$dbSchemaType]))) {
                    continue;
                }
                foreach ($this->infos[$dbSchemaType] as $tableName) {
                    if (!isset($primaryTableToFile[$tableName])) {
                        continue;
                    }
                    if ($primaryTableToFile[$tableName] === $vendorFile) {
                        $primaryDefinitionsInThisFile[$tableName] = $tableName;
                    }
                    if (
                        $primaryTableToFile[$tableName] !== $vendorFile
                        && !str_starts_with($vendorFile, 'vendor/magento/')
                    ) {
                        $this->warnings[$dbSchemaType][$tableName] = $tableName;
                        unset($this->infos[$dbSchemaType][$tableName]);
                    }
                }
            }
            unset($dbSchemaType, $tableName);

            /*
             * Flag if a base table definition changes, when there are third party db_schema.xml modifying that table
             *
             * Just in case you have some db_schema change in a custom module to fix something in the core
             *
             * It may no longer be necessary, or may need tweaked.
             */
            if (empty($primaryDefinitionsInThisFile)) {
                return;
            }

            $dbSchemaAlterations = $this->m2->getDbSchemaThirdPartyAlteration();
            foreach ($primaryDefinitionsInThisFile as $primaryTableBeingModified) {
                if (!isset($dbSchemaAlterations[$primaryTableBeingModified])) {
                    continue;
                }
                foreach ($dbSchemaAlterations[$primaryTableBeingModified] as $thirdPartyDbSchemaModifyingTable) {
                    $this->warnings[Checks::TYPE_DB_SCHEMA_TARGET_CHANGED][]
                        = "$thirdPartyDbSchemaModifyingTable ($primaryTableBeingModified)";
                }
            }
            unset($primaryTableBeingModified, $thirdPartyDbSchemaModifyingTable);
        } catch (\Throwable $throwable) {
            throw new \InvalidArgumentException('db_schema.xml not parseable: ' . $throwable->getMessage());
        }
    }
}
