<?php
/**
 * Aplica bloqueio imediato e agenda o crontab futuro
 * Bloqueia portas no switch via SNMP e registra no banco de dados
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
$motivo = $_POST['motivo'] ?? 'Bloqueio executado pelo professor';
$tempo_bloqueio_horas = $_POST['tempo_bloqueio'] ?? 1;

try {
    // Buscar máquinas a bloquear
    $maquinas = [];
    
    if ($maquina_id) {
        // Bloqueio individual
        $sql = "SELECT m.*, s.ip, s.comunidade_snmp 
                FROM maquinas m 
                JOIN switches s ON m.switch_id = s.id 
                WHERE m.id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $maquina_id]);
        $maquinas = [$stmt->fetch(PDO::FETCH_ASSOC)];
    } elseif ($sala_id) {
        // Bloqueio por sala
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

    $bloqueadas = 0;
    $erros = [];
    $gerenciador_cron = new GerenciadorCron();

    foreach ($maquinas as $maquina) {
        try {
            // Executar comando SNMP para bloquear
            $comando = sprintf(
                'java -jar /var/www/html/dist/GerenteSNMP.jar %s %s %d bloquear',
                $maquina['ip'],
                $maquina['comunidade_snmp'],
                $maquina['porta_switch']
            );

            $output = shell_exec($comando . ' 2>&1');

            // Verificar sucesso da execução SNMP
            if (stripos($output, 'sucesso') !== false || stripos($output, 'executada') !== false) {
                // Atualizar status no banco
                $update_sql = "UPDATE maquinas SET status = 'bloqueada' WHERE id = :id";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->execute([':id' => $maquina['id']]);

                // Registrar agendamento de desbloqueio
                $data_fim = date('Y-m-d H:i:s', strtotime("+$tempo_bloqueio_horas hours"));
                $insert_sql = "INSERT INTO bloqueios_agendados 
                              (maquina_id, data_inicio, data_fim, motivo, ativo) 
                              VALUES (:maquina_id, NOW(), :data_fim, :motivo, TRUE)";
                $insert_stmt = $db->prepare($insert_sql);
                $insert_stmt->execute([
                    ':maquina_id' => $maquina['id'],
                    ':data_fim' => $data_fim,
                    ':motivo' => $motivo
                ]);

                // Agendar desbloqueio automático
                $gerenciador_cron->agendarBloqueio($maquina['id'], $tempo_bloqueio_horas);

                // Registrar log
                $log_sql = "INSERT INTO logs_acesso 
                           (usuario_ip, acao, descricao, maquina_id) 
                           VALUES (:ip, 'bloqueio', :descricao, :maquina_id)";
                $log_stmt = $db->prepare($log_sql);
                $log_stmt->execute([
                    ':ip' => $_SERVER['REMOTE_ADDR'],
                    ':descricao' => $motivo,
                    ':maquina_id' => $maquina['id']
                ]);

                $bloqueadas++;
            } else {
                $erros[] = "Erro ao bloquear {$maquina['nome']}: " . trim($output);
            }
        } catch (Exception $e) {
            $erros[] = "Erro ao processar {$maquina['nome']}: " . $e->getMessage();
        }
    }

    // Preparar resposta
    $resposta = [
        'sucesso' => true,
        'bloqueadas' => $bloqueadas,
        'total' => count($maquinas),
        'mensagem' => "$bloqueadas de " . count($maquinas) . " máquina(s) bloqueada(s)"
    ];

    if (!empty($erros)) {
        $resposta['erros'] = $erros;
    }

    http_response_code(200);
    echo json_encode($resposta);

} catch (Exception $e) {
    error_log('Erro em bloquear.php: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['erro' => 'Erro no servidor: ' . $e->getMessage()]));
}
?>
