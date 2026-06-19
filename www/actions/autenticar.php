<?php
/**
 * Processa o login e senha do principal autorizado
 * Valida credenciais e inicia sessão segura
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/db.php';
require_once '../includes/seguranca.php';

$db_instance = new Database();
$db = $db_instance->conectar();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    die(json_encode(['erro' => 'Método não permitido']));
}

$nome_usuario = $_POST['usuario'] ?? '';
$senha = $_POST['senha'] ?? '';

if (empty($nome_usuario) || empty($senha)) {
    http_response_code(400);
    die(json_encode(['erro' => 'Usuário e senha são obrigatórios']));
}

try {
    // Buscar usuário no banco
    $sql = "SELECT * FROM usuarios_autorizados WHERE nome = :nome AND ativo = TRUE LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':nome' => $nome_usuario]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        // Registrar tentativa falha
        $validador = new ValidadorSeguranca($db);
        $ip = $validador->obterIPCliente();
        $mac = $validador->obterMACCliente($ip);
        $validador->registrarTentativaAcesso($ip, $mac, [
            'autorizado' => false,
            'mensagem' => 'Usuário não encontrado'
        ]);

        http_response_code(401);
        die(json_encode(['erro' => 'Usuário ou senha incorretos']));
    }

    // Verificar senha (usando password_verify para senhas hasheadas)
    if (!password_verify($senha, $usuario['senha_hash'])) {
        // Registrar tentativa falha
        $validador = new ValidadorSeguranca($db);
        $ip = $validador->obterIPCliente();
        $mac = $validador->obterMACCliente($ip);
        $validador->registrarTentativaAcesso($ip, $mac, [
            'autorizado' => false,
            'mensagem' => 'Senha incorreta'
        ]);

        http_response_code(401);
        die(json_encode(['erro' => 'Usuário ou senha incorretos']));
    }

    // Verificar se IP está autorizado
    $validador = new ValidadorSeguranca($db);
    $ip = $validador->obterIPCliente();
    $resultado_validacao = $validador->validarIPMAC($ip);

    if (!$resultado_validacao['autorizado']) {
        // Registrar tentativa falha
        $mac = $validador->obterMACCliente($ip);
        $validador->registrarTentativaAcesso($ip, $mac, [
            'autorizado' => false,
            'mensagem' => 'IP não autorizado: ' . $ip
        ]);

        http_response_code(403);
        die(json_encode(['erro' => 'Seu IP não está autorizado: ' . $ip]));
    }

    // Autenticação bem-sucedida
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['ip_validado'] = $ip;
    $_SESSION['tempo_sessao'] = time();

    // Registrar sucesso
    $mac = $validador->obterMACCliente($ip);
    $validador->registrarTentativaAcesso($ip, $mac, [
        'autorizado' => true,
        'mensagem' => 'Login bem-sucedido'
    ]);

    // Responder com sucesso
    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Login realizado com sucesso',
        'usuario' => $usuario['nome'],
        'redirect' => 'dashboard.php'
    ]);

} catch (Exception $e) {
    error_log('Erro em autenticar.php: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['erro' => 'Erro no servidor']));
}
?>
