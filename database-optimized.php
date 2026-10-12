<?php
class DatabaseOptimizer {
    private $conn;
    private $cache;

    public function __construct($conn, $cache = null) {
        $this->conn = $conn;
        $this->cache = $cache ?: new SimpleCache();
    }

    public function paginate($sql, $page = 1, $perPage = 50) {
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        $offset = ($page - 1) * $perPage;

        $countSql = preg_replace('/^\s*SELECT\s+/is', 'SELECT COUNT(*) AS total FROM ', $sql, 1);
        $countSql = preg_replace('/\s+ORDER\s+BY\s+.*$/is', '', $countSql);

        $countResult = $this->conn->query($countSql);
        $countRow = $countResult->fetch_assoc();
        $total = (int) ($countRow['total'] ?? 0);

        $query = $sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $result = $this->conn->query($query);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return [
            'data' => $rows,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage))
        ];
    }
}
