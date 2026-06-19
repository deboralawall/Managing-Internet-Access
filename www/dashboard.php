<?php
/**
 * Painel de controle visual (Bloqueio coletivo por sala ou individual)
 * Interface principal para gerenciamento de portas
 */

session_start();

// Validar sessão
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'includes/db.php';
$db_instance = new Database();
$db = $db_instance->conectar();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Controle - Gerenciador de Rede</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Painel de Controle de Rede</h1>
            <p>
                Bem-vindo, <strong><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></strong>
                | IP: <?php echo htmlspecialchars($_SESSION['ip_validado']); ?>
            </p>
        </header>

        <main>
            <div id="dashboard-grid" class="dashboard-grid">
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                    <div class="spinner"></div>
                    <p>Carregando dados das máquinas...</p>
                </div>
            </div>
        </main>

        <footer>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>Sistema de Gerenciamento de Redes</strong><br>
                    Última atualização: <span id="ultima-atualizacao">--:--:--</span>
                </div>
                <div>
                    <a href="logout.php" class="btn btn-logout" 
                       style="display: inline-block; padding: 8px 16px; text-decoration: none;">
                        🚪 Sair
                    </a>
                </div>
            </div>
        </footer>
    </div>

    <script src="assets/js/monitor.js"></script>
    <script>
        // Atualizar timestamp a cada segundo
        setInterval(() => {
            const agora = new Date();
            document.getElementById('ultima-atualizacao').textContent = agora.toLocaleTimeString();
        }, 1000);

        // Teste de conexão ao carregar página
        window.addEventListener('load', () => {
            console.log('Dashboard carregado. Monitor de portas iniciado.');
        });
    </script>
</body>
</html>
