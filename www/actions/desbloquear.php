<?php
/**
 * Desbloqueia manualmente e limpa o agendamento do cron
 * Remove bloqueio de portas no switch e cancela desbloqueio automático
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/db.php';
require_once '../includes/funcoes-cron.php';

// Validar sessão
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    die(json_encode(['erro' => 'Sessão expirada']));
}

$db_instance = new Database();
$db = $db_instance->conectar();

$maquina_id = $_POST['maquina_id'] ?? null;
$sala_id = $_POST['sala_id'] ?? null;

try {
    // Buscar máquinas a desbloquear
    $maquinas = [];
    
    if ($maquina_id) {
        // Desbloqueio individual
        $sql = "SELECT m.*, s.ip, s.comunidade_snmp 
                FROM maquinas m 
                JOIN switches s ON m.switch_id = s.id 
                WHERE m.id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $maquina_id]);
        $maquinas = [$stmt->fetch(PDO::FETCH_ASSOC)];
    } elseif ($sala_id) {
        // Desbloqueio por sala
        $sql = "SELECT m.*, s.ip, s.comunidade_snmp 
                FROM maquinas m 
                JOIN switches s ON m.switch_id = s.id 
                WHERE m.sala_id = :sala_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':sala_id' => $sala_id]);
        $maquinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        http_response_code(400);
        die(json_encode(['erro' => 'Especifique maquina_id ou sala_id']));
    }

    if (empty($maquinas)) {
        http_response_code(404);
        die(json_encode(['erro' => 'Máquina(s) não encontrada(s)']));
    }

    $desbloqueadas = 0;
    $erros = [];
    $gerenciador_cron = new GerenciadorCron();

    foreach ($maquinas as $maquina) {
        try {
            // Executar comando SNMP para desbloquear
            $comando = sprintf(
                'java -jar /var/www/html/dist/GerenteSNMP.jar %s %s %d desbloquear',
                $maquina['ip'],
                $maquina['comunidade_snmp'],
                $maquina['porta_switch']
            );

            $output = shell_exec($comando . ' 2>&1');

            // Verificar sucesso da execução SNMP
            if (stripos($output, 'sucesso') !== false || stripos($output, 'executada') !== false) {
                // Atualizar status no banco
                $update_sql = "UPDATE maquinas SET status = 'ativa' WHERE id = :id";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->execute([':id' => $maquina['id']]);

                // Limpar agendamentos de bloqueio
                $delete_sql = "UPDATE bloqueios_agendados SET ativo = FALSE WHERE maquina_id = :id";
                $delete_stmt = $db->prepare($delete_sql);
                $delete_stmt->execute([':id' => $maquina['id']]);

                // Remover do cron
                $gerenciador_cron->removerAgendamento($maquina['id']);

                // Registrar log
                $log_sql = "INSERT INTO logs_acesso 
                           (usuario_ip, acao, descricao, maquina_id) 
                           VALUES (:ip, 'desbloqueio', :descricao, :maquina_id)";
                $log_stmt = $db->prepare($log_sql);
                $log_stmt->execute([
                    ':ip' => $_SERVER['REMOTE_ADDR'],
                    ':descricao' => 'Desbloqueio manual executado',
                    ':maquina_id' => $maquina['id']
                ]);

                $desbloqueadas++;
            } else {
                $erros[] = "Erro ao desbloquear {$maquina['nome']}: " . trim($output);
            }
        } catch (Exception $e) {
            $erros[] = "Erro ao processar {$maquina['nome']}: " . $e->getMessage();
        }
    }

    // Preparar resposta
    $resposta = [
        'sucesso' => true,
        'desbloqueadas' => $desbloqueadas,
        'total' => count($maquinas),
        'mensagem' => "$desbloqueadas de " . count($maquinas) . " máquina(s) desbloqueada(s)"
    ];

    if (!empty($erros)) {
        $resposta['erros'] = $erros;
    }

    http_response_code(200);
    echo json_encode($resposta);

} catch (Exception $e) {
    error_log('Erro em desbloquear.php: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['erro' => 'Erro no servidor: ' . $e->getMessage()]));
}
?>
