<?php
// Adaptador mínimo que expone una API similar a PDO usando mysqli.
class MysqliStatementWrapper
{
    private $stmt;
    private $mysqli;
    private $result;

    public function __construct(mysqli $mysqli, string $sql)
    {
        $this->mysqli = $mysqli;
        $this->stmt = $mysqli->prepare($sql);
        if ($this->stmt === false) {
            throw new RuntimeException('MySQL prepare failed: ' . $mysqli->error);
        }
    }

    public function execute(array $params = null)
    {
        if ($params && count($params) > 0) {
            $types = str_repeat('s', count($params));
            // bind_param requires references
            $refs = [];
            foreach ($params as $k => $v) {
                $refs[$k] = &$params[$k];
            }
            array_unshift($refs, $types);
            if (!call_user_func_array([$this->stmt, 'bind_param'], $refs)) {
                throw new RuntimeException('bind_param failed: ' . $this->stmt->error);
            }
        }

        if (!$this->stmt->execute()) {
            throw new RuntimeException('Statement execute failed: ' . $this->stmt->error);
        }

        $this->result = $this->stmt->get_result();
        return true;
    }

    public function fetchAll($fetchStyle = MYSQLI_ASSOC)
    {
        if (!$this->result) return [];
        return $this->result->fetch_all($fetchStyle);
    }

    public function fetch($fetchStyle = MYSQLI_ASSOC)
    {
        if (!$this->result) return false;
        return $this->result->fetch_assoc();
    }

    public function fetchColumn()
    {
        if (!$this->result) return null;
        $row = $this->result->fetch_row();
        return $row ? $row[0] : null;
    }
}

class MysqliPdoAdapter
{
    private $mysqli;
    private $inTransaction = false;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function prepare(string $sql)
    {
        return new MysqliStatementWrapper($this->mysqli, $sql);
    }

    public function query(string $sql)
    {
        $res = $this->mysqli->query($sql);
        if ($res === false) return false;
        // Wrap result in a simple object with fetch/fetch_assoc/fetch_all
        return new class($res) {
            private $res;
            public function __construct($res) { $this->res = $res; }
            public function fetch($mode = MYSQLI_ASSOC) { return $this->res->fetch_assoc(); }
            public function fetchAll($mode = MYSQLI_ASSOC) { return $this->res->fetch_all($mode); }
            public function fetchColumn() { $r = $this->res->fetch_row(); return $r ? $r[0] : null; }
        };
    }

    public function exec(string $sql)
    {
        $res = $this->mysqli->query($sql);
        if ($res === false) throw new RuntimeException('exec failed: ' . $this->mysqli->error);
        return $this->mysqli->affected_rows;
    }

    public function beginTransaction()
    {
        $this->inTransaction = true;
        $this->mysqli->autocommit(false);
    }

    public function commit()
    {
        $this->mysqli->commit();
        $this->mysqli->autocommit(true);
        $this->inTransaction = false;
    }

    public function rollBack()
    {
        $this->mysqli->rollback();
        $this->mysqli->autocommit(true);
        $this->inTransaction = false;
    }

    public function inTransaction()
    {
        return $this->inTransaction;
    }
}
