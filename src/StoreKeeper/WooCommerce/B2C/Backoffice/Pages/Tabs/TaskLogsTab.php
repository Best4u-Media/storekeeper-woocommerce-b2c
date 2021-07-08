<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class TaskLogsTab extends AbstractLogsTab
{
    const ACTION_RESOLVE = 'action-resolve';
    const ACTION_MASS_RESOLVE = 'action-mass-resolve';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(
            self::ACTION_RESOLVE,
            [$this, 'resolveTaskAction']
        );
        $this->addAction(
            self::ACTION_MASS_RESOLVE,
            [$this, 'resolveTasksAction']
        );
    }

    public function resolveTaskAction()
    {
        if (array_key_exists('selected', $_GET)) {
            $this->resolveTasks(
                [
                    (int) $_GET['selected'],
                ]
            );
        }
        wp_redirect(remove_query_arg(['selected', 'action']));
    }

    public function resolveTasksAction()
    {
        if (array_key_exists('selected', $_POST)) {
            $selected = $this->sanitizeIntArray($_POST['selected']);
            $this->resolveTasks($selected);
        }
        wp_redirect(remove_query_arg(['selected', 'action']));
    }

    private function resolveTasks(array $taskIds)
    {
        global $wpdb;

        $taskIds = $this->sanitizeIntArray($taskIds);
        $in = "'".implode("','", $taskIds)."'";
        $update = TaskModel::getUpdateHelper()
            ->cols(['status' => TaskHandler::STATUS_NEW])
            ->where("id IN ($in)");

        $query = TaskModel::prepareQuery($update);

        $affectedRows = $wpdb->query($query);

        TaskModel::ensureAffectedRows($affectedRows);
    }

    public function render(): void
    {
        list($whereClauses, $whereValues) = $this->getTaskWhereClauses();
        $this->items = $this->fetchData(TaskModel::class, $whereClauses, $whereValues);
        $this->count = TaskModel::count($whereClauses, $whereValues);

        $this->renderTaskSimpleTypeFilter();
        $this->renderTaskStatusFilter();

        $url = $this->getActionUrl(self::ACTION_MASS_RESOLVE);
        $url = esc_attr($url);
        echo "<form action='$url' method='post'>";

        $this->renderTaskMassAction();

        $this->renderPagination();

        $this->renderTable(
            [
                [
                    'title' => 'massAction',
                    'key' => 'massAction',
                    'headerFunction' => [$this, 'renderSelectAll'],
                    'bodyFunction' => [$this, 'renderSelectTask'],
                ],
                [
                    'title' => __('ID', I18N::DOMAIN),
                    'key' => 'id',
                ],
                [
                    'title' => __('Message', I18N::DOMAIN),
                    'key' => 'title',
                ],
                [
                    'title' => __('Date', I18N::DOMAIN),
                    'key' => 'date_created',
                ],
                [
                    'title' => __('Log type', I18N::DOMAIN),
                    'key' => 'type',
                ],
                [
                    'title' => __('Status', I18N::DOMAIN),
                    'key' => 'status',
                    'bodyFunction' => [$this, 'renderTaskStatus'],
                ],
                [
                    'title' => __('Times ran', I18N::DOMAIN),
                    'key' => 'times_ran',
                ],
                [
                    'title' => __('Action', I18N::DOMAIN),
                    'key' => 'action',
                    'bodyFunction' => [$this, 'renderTaskActions'],
                ],
            ]
        );

        echo '</form>';

        $this->renderPagination();
    }

    public function renderSelectAll()
    {
        echo <<<HTML
<input type="checkbox" id="select-all">
<script>
    (function () {
        const all = document.getElementById('select-all');
        all.onclick = function () {
            document.querySelectorAll('[data-select]')
                .forEach(element => {element.checked = this.checked});
        }
    })()
</script>
HTML;
    }

    public function renderSelectTask($value, $task)
    {
        $id = esc_attr($task['id']);
        echo <<<HTML
<input type="checkbox" value="$id" name="selected[]" data-select>
HTML;
    }

    public function renderTaskActions($value, $task)
    {
        $id = $task['id'];
        $status = $task['status'];
        switch ($status) {
            case TaskHandler::STATUS_NEW:
            case TaskHandler::STATUS_SUCCESS:
                echo <<<HTML
<div class="storekeeper-status">
    <span class="storekeeper-status-success"></span>
</div>
HTML;
                break;
            case TaskHandler::STATUS_PROCESSING:
            case TaskHandler::STATUS_FAILED:
                $retry = esc_html__('retry', I18N::DOMAIN);
                $url = add_query_arg(
                    'selected',
                    $id,
                    $this->getActionUrl(self::ACTION_RESOLVE)
                );
                $url = esc_attr($url);

                echo <<<HTML
<div class="storekeeper-status">
    <span class="storekeeper-status-danger"></span>
    <a href="$url">$retry</a>
</div>
HTML;
                break;
        }
    }

    public function renderTaskStatus($value, $task)
    {
        if (TaskHandler::STATUS_FAILED === $task['status']) {
            echo '<a class="dialog-logs" href="javascript:;" data-id="'.$task['id'].'">'.TaskHandler::getStatusLabel($task['status']).'</a>';
            if ($errorOutput = unserialize($task['meta_data'])) {
                echo '<div id="error-message-'.$task['id'].'" style="display: none">
                        <h3><strong style="color:darkred">'.esc_html($errorOutput['exception-class']).': '.esc_html($errorOutput['exception-message']).'</strong></h3>
                        '.__('Stack Trace', I18N::DOMAIN).':
                        <br>
                        <pre>'.esc_html($errorOutput['exception-trace']).'</pre>
                    </div>';
            }
        } else {
            echo TaskHandler::getStatusLabel($task['status']);
        }
    }

    private function getTaskWhereClauses(): array
    {
        $whereClauses = [];
        $whereValues = [];

        $status = $this->getRequestStatus();
        if (in_array($status, TaskHandler::STATUSES)) {
            $whereClauses[] = 'status = :status';
            $whereValues['status'] = $status;
        }

        $taskType = $this->getRequestTaskType();
        if (in_array($taskType, TaskHandler::TYPE_GROUPS)) {
            $whereClauses[] = 'type_group = :type_group';
            $whereValues['type_group'] = $taskType;
        }

        $taskId = $this->getRequestTaskId();
        if ($taskId) {
            $whereClauses[] = "id = $taskId";
        }

        return [$whereClauses, $whereValues];
    }

    private function renderTaskSimpleTypeFilter()
    {
        $currentType = $this->getRequestTaskType();
        $optionLabel = esc_html__('Select log type', I18N::DOMAIN);
        $optionHtml = "<option value=''>$optionLabel</option>";
        foreach (TaskHandler::TYPE_GROUPS as $type) {
            $selected = $currentType === $type ? 'selected' : '';
            $label = esc_html(TaskHandler::getTypeGroupTitle($type));
            $type = esc_attr($type);
            $optionHtml .= "<option value='$type' $selected>$label</option>";
        }

        $hiddenHtml = $this->getHiddenInputs(['task-type']);
        $filter = esc_html__('filter', I18N::DOMAIN);
        echo <<<HTML
<form class="actions" style="display: inline;">
    $hiddenHtml
    <select name="task-type" id="task-type" class="postform">$optionHtml</select>
    <button type="submit" class="button">$filter</button>
</form>
HTML;
    }

    private function renderTaskStatusFilter()
    {
        $currentStatus = $this->getRequestStatus();
        $optionLabel = __('Select log status', I18N::DOMAIN);
        $optionHtml = "<option value=''>$optionLabel</option>";
        foreach (TaskHandler::STATUSES as $status) {
            $selected = $currentStatus === $status ? 'selected' : '';
            $label = esc_html(TaskHandler::getStatusLabel($status));
            $status = esc_attr($status);
            $optionHtml .= "<option value='$status' $selected>$label</option>";
        }

        $hiddenHtml = $this->getHiddenInputs(['task-status']);
        $filter = esc_html__('filter', I18N::DOMAIN);
        echo <<<HTML
<form class="actions" style="display: inline;">
    $hiddenHtml
    <select name="task-status" id="task-status" class="postform">$optionHtml</select>
    <button type="submit" class="button">$filter</button>
</form>
HTML;
    }

    private function renderTaskMassAction()
    {
        $resolve = esc_html__('Resolve selected', I18N::DOMAIN);
        echo <<<HTML
    <button type="submit" class="button storekeeper-resolve">$resolve</button>
HTML;
    }

    private function getHiddenInputs(array $exclude = []): string
    {
        $html = '';

        $queries = ['page', 'tab', 'limit', 'table-index', 'task-type', 'task-status'];
        foreach ($queries as $query) {
            if (in_array($query, $exclude)) {
                continue;
            }

            if (isset($_REQUEST[$query])) {
                $value = esc_attr($_REQUEST[$query]);
                $query = esc_attr($query);
                $html .= "<input type='hidden' name='$query' value='$value' />";
            }
        }

        return $html;
    }

    private function sanitizeIntArray(array $taskIds): array
    {
        $taskIds = array_map('intval', $taskIds);

        return $taskIds;
    }

    private function getRequestStatus(): string
    {
        if (isset($_REQUEST['task-status'])) {
            return sanitize_key($_REQUEST['task-status']);
        }

        return '';
    }

    private function getRequestTaskType(): string
    {
        if (isset($_REQUEST['task-type'])) {
            return sanitize_key($_REQUEST['task-type']);
        }

        return '';
    }

    private function getRequestTaskId(): ?int
    {
        if (isset($_REQUEST['task-id'])) {
            return (int) $_REQUEST['task-id'];
        }

        return null;
    }
}
