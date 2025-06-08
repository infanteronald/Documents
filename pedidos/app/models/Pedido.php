<?php

/**
 * Modelo Pedido
 * Modelo completo para gestión de pedidos con integración Bold PSE
 */

class Pedido
{
    private $conn;

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    /**
     * Crear un nuevo pedido
     */
    public function crear($datos)
    {
        $stmt = $this->conn->prepare("INSERT INTO pedidos_detal (cliente, telefono, direccion, total, fecha, estado) VALUES (?, ?, ?, ?, NOW(), ?)");
        $estado = "pendiente";
        $stmt->bind_param("sssds", $datos["cliente"], $datos["telefono"], $datos["direccion"], $datos["total"], $estado);

        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }

        throw new Exception("Error al crear pedido");
    }

    /**
     * Obtener pedido por ID
     */
    public function obtenerPorId($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM pedidos_detal WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Alias para obtenerPorId (requerido por BoldController)
     */
    public function getById($id)
    {
        return $this->obtenerPorId($id);
    }

    /**
     * Actualizar estado del pedido
     */
    public function actualizarEstado($id, $estado, $notas = "")
    {
        $stmt = $this->conn->prepare("UPDATE pedidos_detal SET estado = ?, notas = ? WHERE id = ?");
        $stmt->bind_param("ssi", $estado, $notas, $id);

        return $stmt->execute();
    }

    /**
     * Alias para actualizarEstado (requerido por BoldController)
     */
    public function updateStatus($id, $status, $notes = "")
    {
        return $this->actualizarEstado($id, $status, $notes);
    }

    /**
     * Actualizar pedido completo (requerido por BoldController)
     */
    public function update($id, $data)
    {
        $fields = [];
        $values = [];
        $types = "";

        foreach ($data as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
            $types .= is_numeric($value) ? "d" : "s";
        }

        $values[] = $id;
        $types .= "i";

        $sql = "UPDATE pedidos_detal SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$values);

        return $stmt->execute();
    }

    /**
     * Obtener pedido por ID de transacción Bold (requerido por BoldController)
     */
    public function getByBoldTransactionId($transactionId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM pedidos_detal WHERE bold_transaction_id = ?");
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Asociar pedido con transacción Bold
     */
    public function setBoldTransactionId($pedidoId, $transactionId)
    {
        $stmt = $this->conn->prepare("UPDATE pedidos_detal SET bold_transaction_id = ? WHERE id = ?");
        $stmt->bind_param("si", $transactionId, $pedidoId);

        return $stmt->execute();
    }

    /**
     * Obtener pedidos con filtros
     */
    public function obtenerConFiltros($filtro, $buscar, $offset, $limite)
    {
        $where = $this->construirWhere($filtro, $buscar);

        $sql = "SELECT * FROM pedidos_detal WHERE $where ORDER BY fecha DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $limite, $offset);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Contar pedidos con filtros
     */
    public function contarConFiltros($filtro, $buscar)
    {
        $where = $this->construirWhere($filtro, $buscar);

        $sql = "SELECT COUNT(*) as total FROM pedidos_detal WHERE $where";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();

        return $row['total'];
    }

    /**
     * Obtener todos los pedidos
     */
    public function obtenerTodos()
    {
        $sql = "SELECT * FROM pedidos_detal ORDER BY fecha DESC";
        $result = $this->conn->query($sql);

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Archivar pedido
     */
    public function archivar($id)
    {
        return $this->actualizarEstado($id, "archivado");
    }

    /**
     * Restaurar pedido archivado
     */
    public function restaurar($id)
    {
        return $this->actualizarEstado($id, "pendiente");
    }

    /**
     * Eliminar pedido (físicamente)
     */
    public function eliminar($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM pedidos_detal WHERE id = ?");
        $stmt->bind_param("i", $id);

        return $stmt->execute();
    }

    /**
     * Obtener estadísticas de pedidos
     */
    public function obtenerEstadisticas()
    {
        $stats = [];

        // Total de pedidos
        $result = $this->conn->query("SELECT COUNT(*) as total FROM pedidos_detal WHERE estado != 'archivado'");
        $stats['total'] = $result->fetch_assoc()['total'];

        // Pedidos por estado
        $result = $this->conn->query("SELECT estado, COUNT(*) as cantidad FROM pedidos_detal WHERE estado != 'archivado' GROUP BY estado");
        while ($row = $result->fetch_assoc()) {
            $stats['por_estado'][$row['estado']] = $row['cantidad'];
        }

        // Pedidos de hoy
        $result = $this->conn->query("SELECT COUNT(*) as hoy FROM pedidos_detal WHERE DATE(fecha) = CURDATE() AND estado != 'archivado'");
        $stats['hoy'] = $result->fetch_assoc()['hoy'];

        return $stats;
    }

    /**
     * Construir cláusula WHERE para filtros
     */
    private function construirWhere($filtro, $buscar)
    {
        switch ($filtro) {
            case "hoy":
                $where = "DATE(fecha) = CURDATE() AND estado!=\"archivado\"";
                break;
            case "semana":
                $where = "YEARWEEK(fecha,1) = YEARWEEK(CURDATE(),1) AND estado!=\"archivado\"";
                break;
            case "mes":
                $where = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado!=\"archivado\"";
                break;
            case "archivados":
                $where = "estado=\"archivado\"";
                break;
            default:
                $where = "estado!=\"archivado\"";
        }

        if (!empty($buscar)) {
            $where .= " AND (cliente LIKE \"%$buscar%\" OR telefono LIKE \"%$buscar%\" OR direccion LIKE \"%$buscar%\")";
        }

        return $where;
    }
}
