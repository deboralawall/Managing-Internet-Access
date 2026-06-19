<?php
/**
 * Funções auxiliares para injetar/remover regras no Crontab Linux
 * Gerencia agendamentos de bloqueio/desbloqueio automático
 */

class GerenciadorCron {
    private $crontab_file = '/tmp/crontab_backup.txt';
    private $usuario_cron = 'www-data'; // Usuário que executa o PHP

    /**
     * Obtém as linhas atuais do crontab
     */
    private function obterCrontabAtual() {
        exec("crontab -u {$this->usuario_cron} -l 2>/dev/null", $output, $retorno);
        
        if ($retorno === 0) {
            return $output;
        }
        return [];
    }

    /**
     * Salva o crontab
     */
    private function salvarCrontab($linhas) {
        $conteudo = implode("\n", $linhas) . "\n";
        file_put_contents($this->crontab_file, $conteudo);
        
        $comando = "crontab -u {$this->usuario_cron} {$this->crontab_file}";
        exec($comando, $output, $retorno);
        
        unlink($this->crontab_file);
        
        return $retorno === 0;
    }

    /**
     * Injeta uma regra de agendamento de bloqueio
     */
    public function agendarBloqueio($maquina_id, $tempo_bloqueio_horas = 1) {
        try {
            $linhas = $this->obterCrontabAtual();
            
            // Criar linha de cron para desbloquear automático
            $tempo_desbloqueio = date('H:i', strtotime("+$tempo_bloqueio_horas hours"));
            $comando_cron = "0 {$tempo_desbloqueio} * * * /usr/bin/php /var/www/html/actions/desbloquear_auto.php $maquina_id";
            
            // Evitar duplicatas
            $existe = false;
            foreach ($linhas as $linha) {
                if (strpos($linha, "desbloquear_auto.php $maquina_id") !== false) {
                    $existe = true;
                    break;
                }
            }
            
            if (!$existe) {
                $linhas[] = $comando_cron;
                if ($this->salvarCrontab($linhas)) {
                    return ['sucesso' => true, 'mensagem' => 'Desbloqueio automático agendado'];
                } else {
                    return ['sucesso' => false, 'mensagem' => 'Erro ao agendar desbloqueio'];
                }
            }
            
            return ['sucesso' => true, 'mensagem' => 'Agendamento já existe'];
        } catch (Exception $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * Remove uma regra de agendamento de bloqueio
     */
    public function removerAgendamento($maquina_id) {
        try {
            $linhas = $this->obterCrontabAtual();
            $linhas_filtradas = [];
            
            // Remover linhas relacionadas a esta máquina
            foreach ($linhas as $linha) {
                if (strpos($linha, "desbloquear_auto.php $maquina_id") === false && trim($linha) !== '') {
                    $linhas_filtradas[] = $linha;
                }
            }
            
            if ($this->salvarCrontab($linhas_filtradas)) {
                return ['sucesso' => true, 'mensagem' => 'Agendamento removido'];
            } else {
                return ['sucesso' => false, 'mensagem' => 'Erro ao remover agendamento'];
            }
        } catch (Exception $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * Lista todos os agendamentos de cron
     */
    public function listarAgendamentos() {
        try {
            $linhas = $this->obterCrontabAtual();
            $agendamentos = [];
            
            foreach ($linhas as $linha) {
                if (strpos($linha, 'desbloquear_auto.php') !== false) {
                    $agendamentos[] = [
                        'comando' => $linha,
                        'ativo' => true
                    ];
                }
            }
            
            return [
                'sucesso' => true,
                'total' => count($agendamentos),
                'agendamentos' => $agendamentos
            ];
        } catch (Exception $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * Executa comando de desbloqueio via SNMP
     */
    public function executarDesbloqueioSNMP($maquina_id) {
        global $db;
        
        try {
            // Buscar informações da máquina
            $sql = "SELECT m.*, s.ip, s.comunidade_snmp 
                    FROM maquinas m 
                    JOIN switches s ON m.switch_id = s.id 
                    WHERE m.id = :id";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $maquina_id]);
            $maquina = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$maquina) {
                return ['sucesso' => false, 'mensagem' => 'Máquina não encontrada'];
            }
            
            // Executar comando SNMP
            $comando = sprintf(
                'java -jar /var/www/html/dist/GerenteSNMP.jar %s %s %d desbloquear',
                $maquina['ip'],
                $maquina['comunidade_snmp'],
                $maquina['porta_switch']
            );
            
            $output = shell_exec($comando . ' 2>&1');
            
            if (stripos($output, 'sucesso') !== false || stripos($output, 'executada') !== false) {
                // Atualizar status no banco
                $update_sql = "UPDATE maquinas SET status = 'ativa' WHERE id = :id";
                $update_stmt = $db->prepare($update_sql);
                $update_stmt->execute([':id' => $maquina_id]);
                
                return ['sucesso' => true, 'mensagem' => 'Desbloqueio SNMP executado'];
            } else {
                return ['sucesso' => false, 'mensagem' => 'Erro ao executar SNMP: ' . $output];
            }
        } catch (Exception $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()];
        }
    }

    /**
     * Lista agendamentos pelo banco de dados
     */
    public function listarAgendamentosBD() {
        global $db;
        
        try {
            $sql = "SELECT * FROM bloqueios_agendados WHERE ativo = TRUE ORDER BY data_inicio ASC";
            $stmt = $db->query($sql);
            $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'sucesso' => true,
                'total' => count($agendamentos),
                'agendamentos' => $agendamentos
            ];
        } catch (Exception $e) {
            return ['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()];
        }
    }
}

// Funções auxiliares
function agendar_bloqueio($maquina_id, $horas = 1) {
    $gerenciador = new GerenciadorCron();
    return $gerenciador->agendarBloqueio($maquina_id, $horas);
}

function remover_agendamento($maquina_id) {
    $gerenciador = new GerenciadorCron();
    return $gerenciador->removerAgendamento($maquina_id);
}

function listar_agendamentos() {
    $gerenciador = new GerenciadorCron();
    return $gerenciador->listarAgendamentos();
}
?>
