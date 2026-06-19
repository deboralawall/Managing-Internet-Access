<?php
/**
 * Encerra a sessão segura do professor
 * Limpa todas as variáveis de sessão e cookies
 */

session_start();

// Registrar logout no banco de dados (opcional)
if (isset($_SESSION['usuario_id'])) {
    require_once 'includes/db.php';
    
    try {
        $db_instance = new Database();
        $db = $db_instance->conectar();
        
        $sql = "INSERT INTO logs_acesso (usuario_ip, acao, descricao) 
                VALUES (:ip, 'logout', 'Sessão encerrada')";
        $stmt = $db->prepare($sql);
        $stmt->execute([':ip' => $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
        error_log('Erro ao registrar logout: ' . $e->getMessage());
    }
}

// Destruir sessão
session_destroy();

// Redirecionar para página de login
header('Location: index.php?logout=1');
exit;
?>
