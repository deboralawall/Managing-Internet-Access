<?php
/**
 * Validação obrigatória de IP/MAC da máquina autorizada
 * Verifica se o usuário que está acessando é autorizado
 */

require_once 'db.php';

class ValidadorSeguranca {
    private $db;

    public function __construct($conexao) {
        $this->db = $conexao;
    }

    /**
     * Obtém o IP do cliente
     */
    public function obterIPCliente() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Obtém o MAC address via ARP (funciona apenas em redes locais)
     * Nota: Isso é um exemplo; em produção, pode necessitar de ajustes
     */
    public function obterMACCliente($ip) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $output = shell_exec("arp -a $ip");
            if (preg_match('/([0-9a-f]{2}-[0-9a-f]{2}-[0-9a-f]{2}-[0-9a-f]{2}-[0-9a-f]{2}-[0-9a-f]{2})/i', $output, $matches)) {
                return strtoupper($matches[1]);
            }
        } else {
            // Linux/Unix
            $output = shell_exec("arp -n $ip");
            if (preg_match('/([0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2})/i', $output, $matches)) {
                return strtoupper($matches[1]);
            }
        }
        return null;
    }

    /**
     * Valida se o IP/MAC da máquina é autorizado
     */
    public function validarIPMAC($ip, $mac = null) {
        try {
            $sql = "SELECT * FROM usuarios_autorizados WHERE ip_autorizado = :ip AND ativo = TRUE LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':ip' => $ip]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                return [
                    'autorizado' => false,
                    'mensagem' => 'Seu IP não está autorizado para acessar este painel'
                ];
            }

            // Se MAC foi fornecido, validar também
            if ($mac && $usuario['mac_autorizado'] !== $mac) {
                return [
                    'autorizado' => false,
                    'mensagem' => 'Seu MAC address não corresponde ao IP autorizado'
                ];
            }

            return [
                'autorizado' => true,
                'usuario_id' => $usuario['id'],
                'usuario_nome' => $usuario['nome'],
                'mensagem' => 'Acesso autorizado'
            ];
        } catch (Exception $e) {
            return [
                'autorizado' => false,
                'mensagem' => 'Erro ao validar acesso: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Registra tentativa de acesso
     */
    public function registrarTentativaAcesso($ip, $mac, $resultado, $maquina_id = null) {
        try {
            $sql = "INSERT INTO logs_acesso (usuario_ip, usuario_mac, acao, descricao, maquina_id) 
                    VALUES (:ip, :mac, :acao, :descricao, :maquina_id)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':ip' => $ip,
                ':mac' => $mac,
                ':acao' => $resultado['autorizado'] ? 'autenticacao_sucesso' : 'autenticacao_falha',
                ':descricao' => $resultado['mensagem'],
                ':maquina_id' => $maquina_id
            ]);
        } catch (Exception $e) {
            error_log('Erro ao registrar tentativa de acesso: ' . $e->getMessage());
        }
    }

    /**
     * Bloqueia por IP após múltiplas tentativas falhas
     */
    public function verificarBloqueio($ip) {
        try {
            // Verificar tentativas falhas nos últimos 15 minutos
            $sql = "SELECT COUNT(*) as tentativas FROM logs_acesso 
                    WHERE usuario_ip = :ip 
                    AND acao = 'autenticacao_falha' 
                    AND data_hora > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':ip' => $ip]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado['tentativas'] >= 5) {
                return [
                    'bloqueado' => true,
                    'mensagem' => 'Acesso bloqueado por múltiplas tentativas falhas. Tente novamente em 15 minutos.'
                ];
            }

            return ['bloqueado' => false];
        } catch (Exception $e) {
            return ['bloqueado' => false];
        }
    }
}

// Função auxiliar para validação rápida
function validarAcessoObrigatorio() {
    global $db;
    
    $validador = new ValidadorSeguranca($db->getConexao());
    $ip_cliente = $validador->obterIPCliente();
    
    // Verificar bloqueio
    $bloqueio = $validador->verificarBloqueio($ip_cliente);
    if ($bloqueio['bloqueado']) {
        http_response_code(403);
        die(json_encode(['erro' => $bloqueio['mensagem']]));
    }
    
    // Validar IP/MAC
    $resultado = $validador->validarIPMAC($ip_cliente);
    $mac_cliente = $validador->obterMACCliente($ip_cliente);
    $validador->registrarTentativaAcesso($ip_cliente, $mac_cliente, $resultado);

    if (!$resultado['autorizado']) {
        http_response_code(403);
        die(json_encode(['erro' => $resultado['mensagem']]));
    }

    // Autenticação bem-sucedida, armazenar em sessão
    session_start();
    $_SESSION['usuario_id'] = $resultado['usuario_id'];
    $_SESSION['usuario_nome'] = $resultado['usuario_nome'];
    $_SESSION['ip_validado'] = $ip_cliente;
    
    return $resultado;
}
?>
