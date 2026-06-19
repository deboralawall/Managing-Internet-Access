<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Rede - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-form {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-form h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }

        .login-form p {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .erro-mensagem {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            color: #dc2626;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }

        .sucesso-mensagem {
            background-color: #f0fdf4;
            border: 1px solid #86efac;
            color: #16a34a;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }

        .loading {
            display: none;
            text-align: center;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <h1>🔐 Gerenciador de Rede</h1>
            <p>Autenticação de Professor Autorizado</p>

            <div class="erro-mensagem" id="erro-msg"></div>
            <div class="sucesso-mensagem" id="sucesso-msg"></div>

            <form id="form-login">
                <div class="form-group">
                    <label for="usuario">Usuário:</label>
                    <input type="text" id="usuario" name="usuario" required 
                           placeholder="Nome do professor">
                </div>

                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required 
                           placeholder="Sua senha">
                </div>

                <button type="submit" class="btn-login" id="btn-submit">Entrar</button>

                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Autenticando...</p>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('form-login').addEventListener('submit', async (e) => {
            e.preventDefault();

            const usuario = document.getElementById('usuario').value;
            const senha = document.getElementById('senha').value;
            const btnSubmit = document.getElementById('btn-submit');
            const loading = document.getElementById('loading');
            const erroMsg = document.getElementById('erro-msg');
            const sucessoMsg = document.getElementById('sucesso-msg');

            // Limpar mensagens
            erroMsg.style.display = 'none';
            sucessoMsg.style.display = 'none';

            // Mostrar loading
            btnSubmit.style.display = 'none';
            loading.style.display = 'block';

            try {
                const formData = new FormData();
                formData.append('usuario', usuario);
                formData.append('senha', senha);

                const resposta = await fetch('actions/autenticar.php', {
                    method: 'POST',
                    body: formData
                });

                const dados = await resposta.json();

                if (resposta.ok && dados.sucesso) {
                    sucessoMsg.textContent = dados.mensagem;
                    sucessoMsg.style.display = 'block';
                    
                    setTimeout(() => {
                        window.location.href = dados.redirect;
                    }, 1500);
                } else {
                    erroMsg.textContent = dados.erro || 'Erro ao autenticar';
                    erroMsg.style.display = 'block';
                    btnSubmit.style.display = 'block';
                    loading.style.display = 'none';
                }
            } catch (erro) {
                console.error('Erro:', erro);
                erroMsg.textContent = 'Erro ao conectar com o servidor';
                erroMsg.style.display = 'block';
                btnSubmit.style.display = 'block';
                loading.style.display = 'none';
            }
        });

        // Carregar dados de exemplo (comentado em produção)
        window.addEventListener('load', () => {
            console.log('Página de login carregada');
        });
    </script>
</body>
</html>
