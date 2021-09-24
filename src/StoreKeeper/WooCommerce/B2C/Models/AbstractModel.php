<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Aura\SqlQuery\QueryInterface;
use Exception;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModel;
use StoreKeeper\WooCommerce\B2C\Singletons\QueryFactorySingleton;

/**
 * Docs: https://github.com/auraphp/Aura.SqlQuery/tree/2.x
 * Note: The following docs mostly also works: https://github.com/auraphp/Aura.SqlQuery/blob/3.x/docs/index.md
 * Class AbstractModel.
 */
abstract class AbstractModel implements IModel
{
    const TABLE_VERSION = '1.0.0';

    public static function getTableVersion(): string
    {
        return get_option(static::TABLE_NAME.'_version');
    }

    private static function setTableVersion()
    {
        update_option(static::TABLE_NAME.'_version', static::TABLE_VERSION);
    }

    const TABLE_NAME = 'storekeeper_abstract';

    public static function getTableName(): string
    {
        global $wpdb, $table_prefix;

        // When running scripts without wordpress, wpdb isn't available.
        $prefix = $wpdb ? $wpdb->prefix : $table_prefix;

        return $prefix.static::TABLE_NAME;
    }

    protected static function querySql(string $sql): bool
    {
        global $wpdb;

        if (false === $wpdb->query($sql)) {
            throw new Exception($wpdb->last_error);
        }

        return true;
    }

    public static function ensureTable(): bool
    {
        if (!static::hasTable()) {
            if (static::createTable()) {
                static::setTableVersion();

                return true;
            }

            return false;
        } elseif (static::isTableOutdated()) {
            static::alterTable();
            static::setTableVersion();
        }

        return true;
    }

    protected static function isTableOutdated(): bool
    {
        return version_compare(static::TABLE_VERSION, static::getTableVersion(), '>');
    }

    public static function validateData(array $data, $isUpdate = false): void
    {
        if ($isUpdate && empty($data['id'])) {
            $stringData = json_encode($data);
            throw new Exception('Object is missing ID when updating in: '.$stringData);
        } else {
            foreach (static::getFields() as $key => $required) {
                if ('id' !== $key && $required && !isset($data[$key])) {
                    throw new Exception("Key $key not in object: ".json_encode($data));
                }
            }
        }
    }

    public static function prepareData(array $data): array
    {
        $preparedData = [];

        foreach (static::getFields() as $key => $required) {
            if (!empty($data[$key])) {
                if ('id' !== $key) {
                    $preparedData[$key] = $data[$key];
                }
            }
        }

        return $preparedData;
    }

    protected static function updateDateField(array $data): array
    {
        $data['date_updated'] = current_time('mysql', 1);

        return $data;
    }

    public static function hasTable(): bool
    {
        global $wpdb;

        $tableName = static::getTableName();

        return $wpdb->get_var("SHOW TABLES LIKE '$tableName'") === $tableName;
    }

    public static function create(array $data): int
    {
        global $wpdb;

        static::validateData($data);

        $insert = static::getInsertHelper()
            ->cols(static::prepareData($data));

        $query = static::prepareQuery($insert);

        $affectedRows = $wpdb->query($query);

        static::ensureAffectedRows($affectedRows, true);

        return $wpdb->insert_id;
    }

    public static function read($id): ?array
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['*'])
            ->where('id = :id')
            ->bindValue('id', $id);

        $query = static::prepareQuery($select);

        $row = $wpdb->get_row(
            $query,
            ARRAY_A
        );

        if (empty($row)) {
            return null;
        }

        try {
            static::validateData($row, true);
        } catch (Exception $exception) {
            $name = static::getTableName();
            throw new Exception("Got invalid data from the \"$name\" database.", null, $exception);
        }

        return $row;
    }

    public static function get($id): ?array
    {
        return static::read($id);
    }

    public static function update($id, array $data): void
    {
        global $wpdb;

        $data['id'] = $id;
        static::validateData($data, true);

        $update = static::getUpdateHelper()
            ->cols(static::prepareData($data))
            ->where('id = :id')
            ->bindValue('id', $id);

        $query = static::prepareQuery($update);

        $affectedRows = $wpdb->query($query);

        static::ensureAffectedRows($affectedRows, true);
    }

    public static function delete($id): void
    {
        global $wpdb;
        $affectedRows = $wpdb->delete(
            static::getTableName(),
            [
                'id' => $id,
            ]
        );

        static::ensureAffectedRows($affectedRows, true);
    }

    public static function count(array $whereClauses = [], array $whereValues = []): int
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['count(id)'])
            ->bindValues($whereValues);

        foreach ($whereClauses as $whereClause) {
            $select->where($whereClause);
        }

        return $wpdb->get_var(static::prepareQuery($select));
    }

    public static function ensureAffectedRows($affectedRows, bool $checkRowAmount = false)
    {
        global $wpdb;
        if (false === $affectedRows || is_null($affectedRows)) {
            throw new Exception('Error during last transaction: '.$wpdb->last_error);
        }
        if ($checkRowAmount && $affectedRows <= 0) {
            throw new Exception('No rows where affected.');
        }
    }

    public static function getSelectHelper(): SelectInterface
    {
        return QueryFactorySingleton::getInstance()
            ->newSelect()
            ->from(static::getTableName());
    }

    public static function getInsertHelper(): InsertInterface
    {
        return QueryFactorySingleton::getInstance()
            ->newInsert()
            ->into(static::getTableName());
    }

    public static function getUpdateHelper(): UpdateInterface
    {
        return QueryFactorySingleton::getInstance()
            ->newUpdate()
            ->table(static::getTableName());
    }

    public static function getDeleteHelper(): DeleteInterface
    {
        return QueryFactorySingleton::getInstance()
            ->newDelete()
            ->from(static::getTableName());
    }

    public static function prepareQuery(QueryInterface $query): string
    {
        return QueryFactorySingleton::getInstance()
            ->prepare($query);
    }
}
