<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

require_admin();

$pageTitle = 'Admin Table Manager';

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
$allowedTables = array_values(array_filter($tables, static fn ($table): bool => is_string($table) && $table !== ''));
$selectedTable = $_GET['table'] ?? ($allowedTables[0] ?? '');

$createBlockedTables = ['developer_profiles', 'gamification', 'reviews', 'proposals', 'reputation_scores'];
$readOnlyTables = ['developer_profiles', 'gamification'];

if (!in_array($selectedTable, $allowedTables, true)) {
    $selectedTable = $allowedTables[0] ?? '';
}

$columns = [];
$primaryKey = null;
$rows = [];
$editRow = null;
$editableColumns = [];
$autoIncrementColumn = null;
$canCreate = false;
$canUpdateDelete = false;

if ($selectedTable !== '') {
    $canCreate = !in_array($selectedTable, $createBlockedTables, true);
    $canUpdateDelete = !in_array($selectedTable, $readOnlyTables, true);

    $columnStatement = $pdo->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA '
        . 'FROM INFORMATION_SCHEMA.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $columnStatement->execute([$selectedTable]);
    $columns = $columnStatement->fetchAll();

    foreach ($columns as $column) {
        if (($column['COLUMN_KEY'] ?? '') === 'PRI' && $primaryKey === null) {
            $primaryKey = $column['COLUMN_NAME'];
        }

        if (strpos((string) ($column['EXTRA'] ?? ''), 'auto_increment') !== false) {
            $autoIncrementColumn = $column['COLUMN_NAME'];
        }

        $editableColumns[] = $column;
    }

    if (is_post()) {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            set_flash('danger', 'Invalid CRUD request.');
            redirect(app_url('admin/table_manager.php?table=' . urlencode($selectedTable)));
        }

        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create') {
                if (!$canCreate) {
                    throw new RuntimeException('Create is disabled for this table.');
                }

                $insertColumns = [];
                $insertValues = [];
                $placeholders = [];

                foreach ($editableColumns as $column) {
                    $columnName = $column['COLUMN_NAME'];
                    $isAutoIncrement = $autoIncrementColumn === $columnName;
                    if ($isAutoIncrement) {
                        continue;
                    }

                    $fieldValue = $_POST['col_' . $columnName] ?? null;
                    if ($fieldValue === '') {
                        $fieldValue = null;
                    }

                    $insertColumns[] = '`' . $columnName . '`';
                    $placeholders[] = '?';
                    $insertValues[] = $fieldValue;
                }

                if ($insertColumns) {
                    $insertSql = 'INSERT INTO `' . $selectedTable . '` (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                    $insertStatement = $pdo->prepare($insertSql);
                    $insertStatement->execute($insertValues);
                }

                set_flash('success', 'Row created in ' . $selectedTable . '.');
                redirect(app_url('admin/table_manager.php?table=' . urlencode($selectedTable)));
            }

            if ($action === 'update' && $primaryKey !== null) {
                if (!$canUpdateDelete) {
                    throw new RuntimeException('Update is disabled for this table.');
                }

                $pkValue = $_POST['pk_value'] ?? null;
                if ($pkValue === null || $pkValue === '') {
                    throw new RuntimeException('Missing primary key value.');
                }

                $setParts = [];
                $updateValues = [];

                foreach ($editableColumns as $column) {
                    $columnName = $column['COLUMN_NAME'];
                    if ($columnName === $primaryKey) {
                        continue;
                    }

                    $fieldValue = $_POST['col_' . $columnName] ?? null;
                    if ($fieldValue === '') {
                        $fieldValue = null;
                    }

                    $setParts[] = '`' . $columnName . '` = ?';
                    $updateValues[] = $fieldValue;
                }

                if ($setParts) {
                    $updateValues[] = $pkValue;
                    $updateSql = 'UPDATE `' . $selectedTable . '` SET ' . implode(', ', $setParts) . ' WHERE `' . $primaryKey . '` = ? LIMIT 1';
                    $updateStatement = $pdo->prepare($updateSql);
                    $updateStatement->execute($updateValues);
                }

                set_flash('success', 'Row updated in ' . $selectedTable . '.');
                redirect(app_url('admin/table_manager.php?table=' . urlencode($selectedTable)));
            }

            if ($action === 'delete' && $primaryKey !== null) {
                if (!$canUpdateDelete) {
                    throw new RuntimeException('Delete is disabled for this table.');
                }

                $pkValue = $_POST['pk_value'] ?? null;
                if ($pkValue === null || $pkValue === '') {
                    throw new RuntimeException('Missing primary key value.');
                }

                $deleteSql = 'DELETE FROM `' . $selectedTable . '` WHERE `' . $primaryKey . '` = ? LIMIT 1';
                $deleteStatement = $pdo->prepare($deleteSql);
                $deleteStatement->execute([$pkValue]);

                set_flash('success', 'Row deleted from ' . $selectedTable . '.');
                redirect(app_url('admin/table_manager.php?table=' . urlencode($selectedTable)));
            }
        } catch (Throwable $exception) {
            set_flash('danger', 'CRUD error: ' . $exception->getMessage());
            redirect(app_url('admin/table_manager.php?table=' . urlencode($selectedTable)));
        }
    }

    $editId = $_GET['edit_id'] ?? null;
    if ($editId !== null && $primaryKey !== null && $canUpdateDelete) {
        $editStatement = $pdo->prepare('SELECT * FROM `' . $selectedTable . '` WHERE `' . $primaryKey . '` = ? LIMIT 1');
        $editStatement->execute([$editId]);
        $editRow = $editStatement->fetch();
    }

    $rowsStatement = $pdo->query('SELECT * FROM `' . $selectedTable . '` ORDER BY 1 DESC LIMIT 200');
    $rows = $rowsStatement->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 section-title mb-1">Table manager</h1>
        <p class="text-muted mb-0">Admin permissions are table-based to avoid changing system-generated developer data.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?php echo app_url('admin/dashboard.php'); ?>">Back to admin dashboard</a>
</div>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="card">
            <div class="card-body">
                <h2 class="h6">Tables</h2>
                <div class="list-group">
                    <?php foreach ($allowedTables as $tableName): ?>
                        <a class="list-group-item list-group-item-action <?php echo $selectedTable === $tableName ? 'active' : ''; ?>" href="<?php echo app_url('admin/table_manager.php?table=' . urlencode($tableName)); ?>"><?php echo e($tableName); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <?php if ($selectedTable === ''): ?>
            <div class="alert alert-warning">No tables found in the current database.</div>
        <?php else: ?>
            <?php if ($editRow || $canCreate): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-2"><?php echo $editRow ? 'Update row' : 'Create row'; ?> in <?php echo e($selectedTable); ?></h2>
                        <?php if (!$canCreate && $editRow): ?>
                            <p class="small text-muted mb-3">Create is disabled for this table. You can edit existing records only.</p>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="<?php echo $editRow ? 'update' : 'create'; ?>">
                            <?php if ($editRow && $primaryKey !== null): ?>
                                <input type="hidden" name="pk_value" value="<?php echo e((string) $editRow[$primaryKey]); ?>">
                            <?php endif; ?>

                            <div class="row g-3">
                                <?php foreach ($editableColumns as $column): ?>
                                    <?php
                                    $columnName = $column['COLUMN_NAME'];
                                    $dataType = strtolower((string) $column['DATA_TYPE']);
                                    $isAutoIncrement = $autoIncrementColumn === $columnName;
                                    $isPrimaryKey = $primaryKey === $columnName;
                                    $existingValue = $editRow[$columnName] ?? '';
                                    if (!$editRow && $isAutoIncrement) {
                                        continue;
                                    }
                                    ?>
                                    <div class="col-md-6">
                                        <label class="form-label"><?php echo e($columnName); ?></label>
                                        <?php if (in_array($dataType, ['text', 'mediumtext', 'longtext'], true)): ?>
                                            <textarea class="form-control" name="col_<?php echo e($columnName); ?>" rows="3" <?php echo ($isPrimaryKey && $editRow) ? 'readonly' : ''; ?>><?php echo e((string) $existingValue); ?></textarea>
                                        <?php else: ?>
                                            <input class="form-control" name="col_<?php echo e($columnName); ?>" value="<?php echo e((string) $existingValue); ?>" <?php echo ($isPrimaryKey && $editRow) ? 'readonly' : ''; ?>>
                                        <?php endif; ?>
                                        <div class="form-text"><?php echo e($column['COLUMN_TYPE']); ?><?php echo ($column['IS_NULLABLE'] === 'YES') ? ' | nullable' : ' | required'; ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <button class="btn btn-primary" type="submit"><?php echo $editRow ? 'Update Row' : 'Create Row'; ?></button>
                                <?php if ($editRow): ?>
                                    <a class="btn btn-outline-secondary" href="<?php echo app_url('admin/table_manager.php?table=' . urlencode($selectedTable)); ?>">Cancel Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info mb-4">This table is read-only for admin. You can only view records.</div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Rows in <?php echo e($selectedTable); ?></h2>
                        <span class="badge text-bg-secondary">Showing up to 200</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <th><?php echo e($column['COLUMN_NAME']); ?></th>
                                    <?php endforeach; ?>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <?php $colName = $column['COLUMN_NAME']; ?>
                                            <td><?php echo e(safe_trim_excerpt((string) ($row[$colName] ?? ''), 80)); ?></td>
                                        <?php endforeach; ?>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <?php if ($primaryKey !== null && $canUpdateDelete): ?>
                                                    <a class="btn btn-outline-primary btn-sm" href="<?php echo app_url('admin/table_manager.php?table=' . urlencode($selectedTable) . '&edit_id=' . urlencode((string) $row[$primaryKey])); ?>">Edit</a>
                                                    <form method="post" onsubmit="return confirm('Delete this row?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="pk_value" value="<?php echo e((string) $row[$primaryKey]); ?>">
                                                        <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                                                    </form>
                                                <?php elseif ($primaryKey !== null): ?>
                                                    <span class="small text-muted">View only</span>
                                                <?php else: ?>
                                                    <span class="small text-muted">No primary key</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$rows): ?>
                                    <tr>
                                        <td colspan="99" class="text-muted">No rows found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
