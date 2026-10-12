<?php
/**
 * Database Query Optimization Layer
 * Usage: $db = new OptimizedDatabase($conn, new SimpleCache());
 *        $page = $db->paginate('SELECT * FROM students', $pageNum, 50);
 */
final class OptimizedDatabase {
    private $conn;
    private $cache;

    public function __construct(PgsqlConnection $conn, SimpleCache $cache = null) {
        $this->conn = $conn;
        $this->cache = $cache ?? new SimpleCache();
    }

    /**
     * Paginate query results
     * @param string $sql Base SQL query (without LIMIT/OFFSET)
     * @param int $page Page number (1-based)
     * @param int $perPage Results per page
     * @return array ['data' => [...], 'page' => 1, 'total' => 100, 'perPage' => 50, 'pages' => 2]
     */
    public function paginate($sql, $page = 1, $perPage = 50) {
        $page = max(1, (int)$page);
        $perPage = max(1, min(1000, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        // Get total count (cached)
        $countCacheKey = 'count_' . md5($sql);
        $total = $this->cache->get($countCacheKey);
        if ($total === null) {
            $countSql = preg_replace('/^\s*SELECT\s+.+?\s+FROM\s+/is', 'SELECT COUNT(*) as cnt FROM ', $sql);
            $result = $this->conn->query($countSql);
            $row = $result->fetch_assoc();
            $total = (int)($row['cnt'] ?? 0);
            $this->cache->set($countCacheKey, $total);
        }

        // Get paginated data
        $paginatedSql = $sql . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
        $result = $this->conn->query($paginatedSql);
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        return [
            'data' => $data,
            'page' => $page,
            'total' => $total,
            'perPage' => $perPage,
            'pages' => (int)ceil($total / $perPage)
        ];
    }

    /**
     * Get cached query result
     * @param string $sql SQL query
     * @param array $params Bind parameters
     * @param int $ttl Cache duration in seconds
     * @return PgsqlResult
     */
    public function cachedQuery($sql, array $params = [], $ttl = 3600) {
        $cacheKey = 'query_' . md5($sql . json_encode($params));
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $this->arrayToResult($cached);
        }

        if (empty($params)) {
            $result = $this->conn->query($sql);
        } else {
            $stmt = $this->conn->prepare($sql);
            // Bind parameters
            $types = '';
            foreach ($params as $param) {
                $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        $data = $result->fetch_all();
        $this->cache->set($cacheKey, $data);
        return $this->arrayToResult($data);
    }

    private function arrayToResult($data) {
        return new class($data) {
            private $rows;
            private $position = 0;
            public $num_rows;

            public function __construct($rows) {
                $this->rows = $rows;
                $this->num_rows = count($rows);
            }

            public function fetch_assoc() {
                return $this->rows[$this->position++] ?? null;
            }

            public function fetch_all() {
                $rows = array_slice($this->rows, $this->position);
                $this->position = count($this->rows);
                return $rows;
            }
        };
    }
}
