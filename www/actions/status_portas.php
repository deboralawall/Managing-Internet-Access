<?php
/**
 * Retorna o estado atual das portas em JSON para o AJAX
 * Fornece dados em tempo real do status de todas as máquinas
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/db.php';

// Validar sessão
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Sessão expirada']));
}

$db_instance = new Database();
$db = $db_instance->conectar();

try {
    // Buscar todas as salas com suas máquinas
    $sql = "SELECT 
            s.id as sala_id,
            s.nome as sala_nome,
            m.id,
            m.nome,
            m.ip,
            m.mac_address,
            m.porta_switch,
            m.status,
            sw.id as switch_id,
            sw.ip as switch_ip,
            sw.comunidade_snmp
        FROM salas s
        LEFT JOIN maquinas m ON s.id = m.sala_id
        LEFT JOIN switches sw ON m.switch_id = sw.id
        ORDER BY s.nome, m.nome";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar dados por sala
    $dados_salas = [];
    
    foreach ($resultados as $registro) {
        $sala_id = $registro['sala_id'];
        $sala_nome = $registro['sala_nome'];
        
        if (!isset($dados_salas[$sala_nome])) {
            $dados_salas[$sala_nome] = [
                'id' => $sala_id,
                'nome' => $sala_nome,
                'maquinas' => []
            ];
        }

        // Adicionar máquina se houver
        if ($registro['id'] !== null) {
            $dados_salas[$sala_nome]['maquinas'][] = [
                'id' => $registro['id'],
                'nome' => $registro['nome'],
                'ip' => $registro['ip'],
                'mac_address' => $registro['mac_address'],
                'porta_switch' => $registro['porta_switch'],
                'status' => $registro['status'],
                'switch_id' => $registro['switch_id'],
                'switch_ip' => $registro['switch_ip']
            ];
        }
    }

    // Buscar agendamentos de bloqueio
    $sql_agendamentos = "SELECT * FROM bloqueios_agendados WHERE ativo = TRUE";
    $stmt_agendamentos = $db->prepare($sql_agendamentos);
    $stmt_agendamentos->execute();
    $agendamentos = $stmt_agendamentos->fetchAll(PDO::FETCH_ASSOC);

    // Montar resposta
    $resposta = [
        'salas' => $dados_salas,
        'agendamentos' => $agendamentos,
        'timestamp' => date('Y-m-d H:i:s'),
        'usuario' => $_SESSION['usuario_nome'] ?? 'Usuário Desconhecido'
    ];

    http_response_code(200);
    echo json_encode($resposta);

} catch (Exception $e) {
    error_log('Erro em status_portas.php: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'error' => 'Erro ao buscar status',
        'mensagem' => $e->getMessage()
    ]));
}
?>
