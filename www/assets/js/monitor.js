/**
 * Script AJAX para atualizar o status das portas automaticamente
 * Realiza polling periódico ao servidor para obter status atual
 */

class MonitorPortas {
    constructor(intervaloMs = 5000) {
        this.intervaloMs = intervaloMs;
        this.intervaloId = null;
        this.init();
    }

    /**
     * Inicializa o monitor
     */
    init() {
        console.log('Monitor de Portas iniciado');
        this.atualizarStatus();
        this.iniciarPolling();
        this.setupEventListeners();
    }

    /**
     * Inicia polling periódico
     */
    iniciarPolling() {
        this.intervaloId = setInterval(() => {
            this.atualizarStatus();
        }, this.intervaloMs);
    }

    /**
     * Para o polling
     */
    pararPolling() {
        if (this.intervaloId) {
            clearInterval(this.intervaloId);
            this.intervaloId = null;
        }
    }

    /**
     * Atualiza o status de todas as portas
     */
    atualizarStatus() {
        fetch('actions/status_portas.php')
            .then(response => response.json())
            .then(dados => {
                this.renderizarDados(dados);
            })
            .catch(erro => {
                console.error('Erro ao buscar status:', erro);
                this.mostrarErro('Erro ao conectar com o servidor');
            });
    }

    /**
     * Renderiza os dados de status na interface
     */
    renderizarDados(dados) {
        const container = document.getElementById('dashboard-grid');
        if (!container) return;

        if (dados.error) {
            this.mostrarErro(dados.error);
            return;
        }

        container.innerHTML = '';
        
        for (const sala in dados.salas) {
            const salaData = dados.salas[sala];
            const salaCard = this.criarCardSala(sala, salaData);
            container.appendChild(salaCard);
        }
    }

    /**
     * Cria um card de sala com suas portas
     */
    criarCardSala(nomeSala, salaData) {
        const card = document.createElement('div');
        card.className = 'sala-card';
        card.id = `sala-${salaData.id}`;

        let html = `
            <div class="sala-titulo">
                <span>📍 ${nomeSala}</span>
                <span style="font-size: 12px; color: #999;">
                    (${salaData.maquinas.length} máquinas)
                </span>
            </div>
        `;

        // Renderizar portas
        salaData.maquinas.forEach(maquina => {
            const status = maquina.status === 'bloqueada' ? 'fechada' : 'aberta';
            const statusClass = maquina.status === 'bloqueada' ? 'fechada' : 'aberta';
            const statusTexto = maquina.status === 'bloqueada' ? 'BLOQUEADA' : 'ATIVA';
            
            html += `
                <div class="porta-item ${statusClass}">
                    <div class="porta-info">
                        <div class="porta-nome">${maquina.nome}</div>
                        <div class="porta-detalhe">
                            IP: ${maquina.ip} | Porta: ${maquina.porta_switch}
                        </div>
                    </div>
                    <div class="porta-status">
                        <span style="color: ${statusClass === 'aberta' ? '#28a745' : '#dc3545'}">
                            ${statusTexto}
                        </span>
                        <div class="status-icon ${statusClass}"></div>
                    </div>
                </div>
            `;
        });

        // Controles da sala
        html += `
            <div class="controls">
                <div class="controle-sala">
                    <button class="btn btn-bloquear btn-controle" 
                            onclick="monitor.bloquearSala(${salaData.id})">
                        Bloquear Sala
                    </button>
                </div>
                <div class="controle-sala">
                    <button class="btn btn-desbloquear btn-controle" 
                            onclick="monitor.desbloquearSala(${salaData.id})">
                        Desbloquear Sala
                    </button>
                </div>
            </div>
        `;

        card.innerHTML = html;
        return card;
    }

    /**
     * Bloqueia uma sala inteira
     */
    bloquearSala(salaId) {
        if (!confirm('Tem certeza que deseja BLOQUEAR esta sala?')) return;
        
        this.enviarComando('bloquear', { sala_id: salaId });
    }

    /**
     * Desbloqueia uma sala inteira
     */
    desbloquearSala(salaId) {
        if (!confirm('Tem certeza que deseja DESBLOQUEAR esta sala?')) return;
        
        this.enviarComando('desbloquear', { sala_id: salaId });
    }

    /**
     * Bloqueia uma máquina individual
     */
    bloquearMaquina(maquinaId) {
        if (!confirm('Tem certeza que deseja BLOQUEAR esta máquina?')) return;
        
        this.enviarComando('bloquear', { maquina_id: maquinaId });
    }

    /**
     * Desbloqueia uma máquina individual
     */
    desbloquearMaquina(maquinaId) {
        if (!confirm('Tem certeza que deseja DESBLOQUEAR esta máquina?')) return;
        
        this.enviarComando('desbloquear', { maquina_id: maquinaId });
    }

    /**
     * Envia comando para o servidor
     */
    enviarComando(acao, parametros) {
        const formData = new FormData();
        formData.append('acao', acao);
        Object.keys(parametros).forEach(key => {
            formData.append(key, parametros[key]);
        });

        fetch('actions/' + (acao === 'bloquear' ? 'bloquear.php' : 'desbloquear.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(dados => {
            if (dados.sucesso) {
                this.mostrarSucesso('Operação realizada com sucesso!');
                setTimeout(() => this.atualizarStatus(), 500);
            } else {
                this.mostrarErro(dados.erro || 'Erro ao processar comando');
            }
        })
        .catch(erro => {
            console.error('Erro:', erro);
            this.mostrarErro('Erro ao comunicar com o servidor');
        });
    }

    /**
     * Configura listeners de eventos
     */
    setupEventListeners() {
        // Pausar polling quando a página perde o foco
        window.addEventListener('blur', () => this.pararPolling());
        
        // Retomar polling quando a página ganha o foco
        window.addEventListener('focus', () => {
            if (!this.intervaloId) {
                this.iniciarPolling();
                this.atualizarStatus();
            }
        });
    }

    /**
     * Mostra mensagem de sucesso
     */
    mostrarSucesso(mensagem) {
        this.mostrarNotificacao(mensagem, 'success');
    }

    /**
     * Mostra mensagem de erro
     */
    mostrarErro(mensagem) {
        this.mostrarNotificacao(mensagem, 'error');
    }

    /**
     * Mostra notificação genérica
     */
    mostrarNotificacao(mensagem, tipo = 'info') {
        const notificacao = document.createElement('div');
        notificacao.className = `notificacao notificacao-${tipo}`;
        notificacao.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background-color: ${tipo === 'success' ? '#28a745' : tipo === 'error' ? '#dc3545' : '#667eea'};
            color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        `;
        notificacao.textContent = mensagem;
        
        document.body.appendChild(notificacao);
        
        setTimeout(() => {
            notificacao.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notificacao.remove(), 300);
        }, 3000);
    }
}

// Inicializar monitor quando a página carregar
let monitor;
document.addEventListener('DOMContentLoaded', () => {
    monitor = new MonitorPortas(5000); // Atualizar a cada 5 segundos
});

// Estilos de animação
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
