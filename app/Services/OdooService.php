<?php

namespace App\Services;

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;

class OdooService
{
    private string $url;
    private string $db;
    private string $username;
    private string $apiKey;
    private ?int $uid = null;

    public function __construct(array $config)
    {
        $this->url = rtrim($config['url'], '/');
        $this->db = $config['dbName'];
        $this->username = $config['username'];
        $this->apiKey = $config['apiKey'];
    }

    public function authenticate(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $client = new Client($this->url . '/xmlrpc/2/common');
        $request = new Request('authenticate', [
            new Value($this->db, 'string'),
            new Value($this->username, 'string'),
            new Value($this->apiKey, 'string'),
            new Value([], 'struct'),
        ]);

        $response = $client->send($request);
        if ($response->faultCode()) {
            throw new \RuntimeException('Odoo auth error: ' . $response->faultString());
        }

        $uid = $response->value()->scalarval();
        if (!$uid || $uid === 0) {
            throw new \RuntimeException('Odoo authentication failed - invalid credentials');
        }

        $this->uid = (int) $uid;
        return $this->uid;
    }

    public function getOdooUid(): ?int
    {
        return $this->uid;
    }

    public function fetchProjects(): array
    {
        return $this->searchRead('project.project', ['id', 'name', 'user_id', 'color']);
    }

    public function fetchTasks(int $projectId): array
    {
        return $this->searchRead(
            'project.task',
            ['id', 'name', 'description', 'stage_id', 'user_ids', 'priority', 'date_deadline', 'color'],
            [['project_id', '=', $projectId]]
        );
    }

    public function fetchStageNames(?int $projectId = null): array
    {
        if ($projectId !== null) {
            $projectStages = $this->searchRead(
                'project.task.type',
                ['id', 'name', 'sequence'],
                [['project_ids', 'in', [$projectId]]]
            );
            if (!empty($projectStages)) {
                return $projectStages;
            }
        }
        return $this->searchRead(
            'project.task.type',
            ['id', 'name', 'sequence'],
            [['project_ids', '=', false]]
        );
    }

    /**
     * Fetch ALL tasks for multiple projects in a single call.
     */
    public function fetchAllTasks(array $projectIds): array
    {
        if (empty($projectIds)) {
            return [];
        }
        return $this->searchReadAll(
            'project.task',
            ['id', 'name', 'description', 'stage_id', 'user_ids', 'priority', 'date_deadline', 'color', 'project_id'],
            [['project_id', 'in', $projectIds]]
        );
    }

    /**
     * Fetch ALL timesheets for multiple tasks in batched calls.
     * Returns a flat array of timesheets keyed by nothing (caller maps by taskOdooId).
     */
    public function fetchAllTimesheets(array $taskIds, int $chunkSize = 100): array
    {
        if (empty($taskIds)) {
            return [];
        }
        $allTimesheets = [];
        foreach (array_chunk($taskIds, $chunkSize) as $chunk) {
            $batch = $this->searchReadAll(
                'account.analytic.line',
                ['id', 'name', 'unit_amount', 'date', 'employee_id', 'user_id', 'task_id'],
                [['task_id', 'in', $chunk]]
            );
            foreach ($batch as $ts) {
                $ts['taskOdooId'] = is_array($ts['task_id'] ?? null) ? (int) $ts['task_id'][0] : (int) ($ts['task_id'] ?? 0);
                $allTimesheets[] = $ts;
            }
        }
        return $allTimesheets;
    }

    /**
     * Fetch ALL stages in a single call, including their project assignments.
     * Returns array with stage info + project_ids.
     */
    public function fetchAllStages(): array
    {
        return $this->searchReadAll(
            'project.task.type',
            ['id', 'name', 'sequence', 'project_ids']
        );
    }

    public function fetchUserNames(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $users = $this->searchRead(
            'res.users',
            ['id', 'name', 'login'],
            [['id', 'in', $userIds]]
        );
        $map = [];
        foreach ($users as $u) {
            $rawName = $u['name'] ?? $u['login'] ?? ('User #' . $u['id']);
            $displayName = strpos($rawName, '@') !== false ? explode('@', $rawName)[0] : $rawName;
            $map[$u['id']] = $displayName;
        }
        return $map;
    }

    public function fetchTimesheets(int $taskId): array
    {
        return $this->searchRead(
            'account.analytic.line',
            ['id', 'name', 'unit_amount', 'date', 'employee_id', 'user_id'],
            [['task_id', '=', $taskId]]
        );
    }

    public function fetchFieldSelection(string $model, string $field): array
    {
        $uid = $this->authenticate();
        $client = new Client($this->url . '/xmlrpc/2/object');

        $request = new Request('execute_kw', [
            new Value($this->db, 'string'),
            new Value($uid, 'int'),
            new Value($this->apiKey, 'string'),
            new Value($model, 'string'),
            new Value('fields_get', 'string'),
            self::php2XmlRpc([$field]),
            self::php2XmlRpc(['attributes' => ['selection', 'string', 'type']]),
        ]);

        $response = $client->send($request);
        if ($response->faultCode()) {
            throw new \RuntimeException('Odoo fields_get error: ' . $response->faultString());
        }

        $result = self::decodeValue($response->value());
        $fieldInfo = $result[$field] ?? null;
        if (!isset($fieldInfo['selection'])) {
            return [];
        }

        $items = [];
        foreach ($fieldInfo['selection'] as $pair) {
            $items[] = ['key' => (string) $pair[0], 'value' => (string) $pair[1]];
        }
        return $items;
    }

    public function fetchEmployeeUserId(int $employeeId): ?int
    {
        $uid = $this->authenticate();
        $client = new Client($this->url . '/xmlrpc/2/object');
        $request = new Request('execute_kw', [
            new Value($this->db, 'string'),
            new Value($uid, 'int'),
            new Value($this->apiKey, 'string'),
            new Value('hr.employee', 'string'),
            new Value('read', 'string'),
            new Value([[$employeeId], ['user_id']], 'array'),
        ]);

        $response = $client->send($request);
        if ($response->faultCode()) {
            return null;
        }
        $result = $response->value()->scalarval();
        if (empty($result)) {
            return null;
        }
        $user = $result[0]['user_id'] ?? null;
        if (is_array($user) && count($user) >= 2) {
            return (int) $user[0];
        }
        if (is_int($user)) {
            return $user;
        }
        return null;
    }

    public function fetchEmployees(): array
    {
        $employees = $this->searchRead('hr.employee', ['id', 'name', 'user_id']);
        return array_map(function ($e) {
            return [
                'id' => $e['id'],
                'name' => $e['name'] ?? ('Employee #' . $e['id']),
                'userId' => is_array($e['user_id'] ?? null) ? (int) $e['user_id'][0] : (is_int($e['user_id'] ?? null) ? $e['user_id'] : null),
            ];
        }, $employees);
    }

    public function fetchUsers(): array
    {
        $users = $this->searchRead('res.users', ['id', 'name', 'login']);
        return array_map(function ($u) {
            $rawName = $u['name'] ?? $u['login'] ?? ('User #' . $u['id']);
            $displayName = strpos($rawName, '@') !== false ? explode('@', $rawName)[0] : $rawName;
            return [
                'id' => $u['id'],
                'name' => $displayName,
                'email' => $u['login'] ?? '',
            ];
        }, $users);
    }

    public function updateRecord(string $model, int $id, array $values): bool
    {
        $uid = $this->authenticate();
        $client = new Client($this->url . '/xmlrpc/2/object');
        $request = new Request('execute_kw', [
            new Value($this->db, 'string'),
            new Value($uid, 'int'),
            new Value($this->apiKey, 'string'),
            new Value($model, 'string'),
            new Value('write', 'string'),
            new Value([[$id], self::php2XmlRpc($values)], 'array'),
        ]);

        $response = $client->send($request);
        if ($response->faultCode()) {
            throw new \RuntimeException('Odoo write error: ' . $response->faultString());
        }
        return (bool) $response->value()->scalarval();
    }

    public function createRecord(string $model, array $values): int
    {
        $uid = $this->authenticate();
        $client = new Client($this->url . '/xmlrpc/2/object');
        $request = new Request('execute_kw', [
            new Value($this->db, 'string'),
            new Value($uid, 'int'),
            new Value($this->apiKey, 'string'),
            new Value($model, 'string'),
            new Value('create', 'string'),
            new Value([self::php2XmlRpc($values)], 'array'),
        ]);

        $response = $client->send($request);
        if ($response->faultCode()) {
            throw new \RuntimeException('Odoo create error: ' . $response->faultString());
        }
        return (int) $response->value()->scalarval();
    }

    /**
     * Convert a PHP value to an XML-RPC Value.
     */
    private static function php2XmlRpc($value): Value
    {
        if ($value === null) {
            return new Value('', 'string');
        }
        if (is_int($value)) {
            return new Value($value, 'int');
        }
        if (is_float($value)) {
            return new Value($value, 'double');
        }
        if (is_bool($value)) {
            return new Value($value ? 1 : 0, 'boolean');
        }
        if (is_string($value)) {
            return new Value($value, 'string');
        }
        if (is_array($value)) {
            $isList = array_is_list($value);
            if ($isList) {
                $items = [];
                foreach ($value as $v) {
                    $items[] = self::php2XmlRpc($v);
                }
                return new Value($items, 'array');
            }
            // Struct: build with keys as member names
            $struct = new Value([], 'struct');
            foreach ($value as $k => $v) {
                $struct[$k] = self::php2XmlRpc($v);
            }
            return $struct;
        }
        return new Value('', 'string');
    }

    /**
     * Build XML-RPC params for search_read: domain + options struct.
     * Supports pagination via offset.
     */
    private function searchRead(string $model, array $fields, array $domain = [], int $offset = 0): array
    {
        $uid = $this->authenticate();

        // Build domain array properly
        $domainValues = [];
        foreach ($domain as $clause) {
            $clauseValues = [];
            foreach ($clause as $clauseItem) {
                $clauseValues[] = self::php2XmlRpc($clauseItem);
            }
            $domainValues[] = new Value($clauseValues, 'array');
        }

        // Build options struct
        $fieldValues = [];
        foreach ($fields as $f) {
            $fieldValues[] = new Value($f, 'string');
        }

        // args must be [domain]: an array wrapping the domain array
        $argsDomain = new Value($domainValues, 'array');

        $options = [
            'fields' => new Value($fieldValues, 'array'),
            'limit'  => new Value(500, 'int'),
        ];
        if ($offset > 0) {
            $options['offset'] = new Value($offset, 'int');
        }

        $client = new Client($this->url . '/xmlrpc/2/object');
        $request = new Request('execute_kw', [
            new Value($this->db, 'string'),
            new Value($uid, 'int'),
            new Value($this->apiKey, 'string'),
            new Value($model, 'string'),
            new Value('search_read', 'string'),
            new Value([$argsDomain], 'array'),
            new Value($options, 'struct'),
        ]);

        $response = $client->send($request);
        if ($response->faultCode()) {
            throw new \RuntimeException("Odoo fetch error ({$model}, domain=" . json_encode($domain) . '): ' . $response->faultString());
        }
        $result = $response->value();
        return self::decodeValue($result);
    }

    /**
     * Automatically paginate through all results of a search_read query.
     */
    public function searchReadAll(string $model, array $fields, array $domain = []): array
    {
        $all = [];
        $offset = 0;
        $limit = 500;
        do {
            $batch = $this->searchRead($model, $fields, $domain, $offset);
            $all = array_merge($all, $batch);
            $offset += $limit;
        } while (count($batch) === $limit);
        return $all;
    }

    /**
     * Recursively decode an XML-RPC Value to native PHP types.
     */
    private static function decodeValue($value)
    {
        if (!($value instanceof Value)) {
            return $value;
        }

        $kind = $value->kindOf();

        if ($kind === 'struct' || $kind === 'array') {
            $result = [];
            foreach ($value as $key => $val) {
                $result[$key] = self::decodeValue($val);
            }
            return $result;
        }

        return $value->scalarVal();
    }
}
