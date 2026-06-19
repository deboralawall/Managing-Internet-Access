<?php
/**
 * Conexão PDO com o Banco de Dados MySQL
 * Arquivo centralizado para gerenciar todas as conexões com o banco de dados
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'gerencia_redes';
    private $user = 'root';
    private $password = '';
    private $pdo;

    /**
     * Conecta ao banco de dados
     */
    public function conectar() {
        try {
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8';
            $this->pdo = new PDO($dsn, $this->user, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->pdo;
        } catch (PDOException $e) {
            die('Erro de Conexão: ' . $e->getMessage());
        }
    }

    /**
     * Obtém a conexão
     */
    public function getConexao() {
        if (!$this->pdo) {
            $this->conectar();
        }
        return $this->pdo;
    }

    /**
     * Executa uma query preparada
     */
    public function executar($sql, $parametros = []) {
        try {
            $stmt = $this->getConexao()->prepare($sql);
            $stmt->execute($parametros);
            return $stmt;
        } catch (PDOException $e) {
            die('Erro na Query: ' . $e->getMessage());
        }
    }

    /**
     * Busca um único registro
     */
    public function buscarUm($sql, $parametros = []) {
        $stmt = $this->executar($sql, $parametros);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca múltiplos registros
     */
    public function buscarTodos($sql, $parametros = []) {
        $stmt = $this->executar($sql, $parametros);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insere um registro
     */
    public function inserir($tabela, $dados) {
        $colunas = implode(',', array_keys($dados));
        $placeholders = ':' . implode(',:', array_keys($dados));
        
        $sql = "INSERT INTO $tabela ($colunas) VALUES ($placeholders)";
        $this->executar($sql, $dados);
        
        return $this->getConexao()->lastInsertId();
    }

    /**
     * Atualiza um registro
     */
    public function atualizar($tabela, $dados, $condicao, $parametros_condicao = []) {
        $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($dados)));
        
        $sql = "UPDATE $tabela SET $set WHERE $condicao";
        $parametros = array_merge($dados, $parametros_condicao);
        
        return $this->executar($sql, $parametros)->rowCount();
    }

    /**
     * Deleta um registro
     */
    public function deletar($tabela, $condicao, $parametros = []) {
        $sql = "DELETE FROM $tabela WHERE $condicao";
        return $this->executar($sql, $parametros)->rowCount();
    }
}

// Instância global do banco de dados
$db = new Database();
$conexao = $db->conectar();
?>
