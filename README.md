# Sistema de Gerenciamento de Redes - Bloqueio de Portas SNMP

## 📋 Descrição Geral

Sistema integrado para controle de acesso em laboratórios de redes, permitindo que professores autorizados bloqueiem/desbloqueiem portas de switches através de uma interface web intuitiva.

## 🏗️ Arquitetura do Projeto

### Componentes Principais

```
trabalho-gerencia-redes/
├── Backend Java (SNMP) - Comunicação com switches
├── Backend PHP - Lógica de negócio e API
├── Frontend (HTML/CSS/JS) - Interface visual
└── Database (MySQL) - Persistência de dados
```

## 🚀 Instalação e Setup

### Pré-requisitos

- **Java**: JDK 11+
- **PHP**: 7.4+ com PDO MySQL
- **MySQL**: 5.7+ ou MariaDB 10.3+
- **Linux/Unix**: Para suporte a crontab (opcional para agendamentos)

### 1. Banco de Dados

```bash
# Criar banco de dados
mysql -u root -p < database/banco.sql

# Carregar dados iniciais
mysql -u root -p gerencia_redes < database/povoar.sql
```

### 2. Compilar Gerente SNMP

```bash
cd gerente-snmp-java

# Compilar
javac -cp lib/snmp4j-2.8.18.jar src/br/udesc/gerencia/GerenteSNMP.java

# Gerar JAR
jar cvfm dist/GerenteSNMP.jar src/br/udesc/gerencia/GerenteSNMP.class

# Dar permissão de execução
chmod +x dist/GerenteSNMP.jar
```

### 3. Configurar Servidor Web

```bash
# Copiar arquivos PHP para diretório web
cp -r www/* /var/www/html/

# Ajustar permissões
chmod -R 755 /var/www/html/
chmod -R 644 /var/www/html/*.*

# Criar diretório de logs
mkdir -p /var/www/html/logs
chmod 777 /var/www/html/logs
```

### 4. Configurar PHP

Editar `/var/www/html/includes/db.php` com credenciais do banco:

```php
private $host = 'localhost';
private $db_name = 'gerencia_redes';
private $user = 'root';
private $password = 'sua_senha';
```

## 🔐 Segurança

### Autenticação

- **IP/MAC Whitelist**: Validação obrigatória da máquina autorizada
- **Login com Senha**: Hash bcrypt para armazenamento seguro
- **Sessão HTTP**: Timeout automático após inatividade

### Autorização

- Apenas usuários cadastrados em `usuarios_autorizados` podem acessar
- Bloqueio após 5 tentativas falhas em 15 minutos
- Logs de todas as ações realizadas

## 🔧 Uso

### Interface Web

1. Acesse: `http://seu-servidor/index.php`
2. Digite credenciais (Professor)
3. Selecione sala ou máquina individual
4. Clique em "Bloquear" ou "Desbloquear"

### Linha de Comando (Java)

```bash
java -jar dist/GerenteSNMP.jar <ip_switch> <comunidade> <porta> <acao>

# Exemplo:
java -jar dist/GerenteSNMP.jar 192.168.1.10 public 1 bloquear
```

## 📊 Funcionalidades

### Bloqueio Imediato
- ✅ Porta bloqueada em < 1 segundo via SNMP SET
- ✅ Status atualizado em tempo real no dashboard
- ✅ Registrado no banco de dados com timestamp

### Desbloqueio Automático
- ✅ Agendamento via crontab Linux
- ✅ Desbloqueio manual a qualquer momento
- ✅ Cancelamento de agendamentos anteriores

### Monitoramento
- ✅ AJAX polling a cada 5 segundos
- ✅ Visualização de status de todas as portas
- ✅ Agrupamento por sala
- ✅ Ícones visuais (Verde=Aberta, Vermelho=Bloqueada)

### Logs e Auditoria
- ✅ Registro de todas as ações (login, bloqueio, desbloqueio)
- ✅ IP/MAC do usuário em cada operação
- ✅ Timestamp preciso para auditar

## 🛠️ Troubleshooting

### Erro: "Conexão recusada no switch"

```bash
# Testar conectividade SNMP
snmpget -v2c -c public 192.168.1.10 sysDescr

# Verificar status da porta
snmpwalk -v2c -c public 192.168.1.10 1.3.6.1.2.1.2.2.1.7
```

### Erro: "PHP shell_exec não funcionando"

```bash
# Verificar se shell_exec está habilitado no php.ini
grep "disable_functions" /etc/php/7.4/apache2/php.ini

# Garantir permissões
sudo chown www-data:www-data /var/www/html/dist/GerenteSNMP.jar
sudo chmod 755 /var/www/html/dist/GerenteSNMP.jar
```

### Erro: "Crontab permission denied"

```bash
# Adicionar www-data ao crontab
sudo usermod -a -G www-data www-data

# Editar crontab diretamente
sudo crontab -u www-data -e
```

## 📝 Estrutura de Dados

### Tabelas Principais

**usuarios_autorizados** - Professores com acesso
- id, nome, ip_autorizado, mac_autorizado, senha_hash, ativo

**salas** - Salas do laboratório
- id, nome, descricao, data_criacao

**switches** - Equipamentos de rede
- id, nome, ip, comunidade_snmp, sala_id

**maquinas** - Máquinas de teste
- id, nome, ip, mac_address, porta_switch, status, sala_id

**bloqueios_agendados** - Agendamentos futuro
- id, maquina_id, data_inicio, data_fim, motivo, ativo

**logs_acesso** - Auditoria de ações
- id, usuario_ip, acao, descricao, data_hora

## 📚 Referências

- SNMP4J: http://www.snmp4j.org/
- RFC 3411-3418: SNMP Specifications
- IEEE 802.1D: Spanning Tree Protocol (Ports)

## 📧 Suporte

Para dúvidas ou problemas, consulte a documentação técnica ou entre em contato.

---

**Desenvolvido como Trabalho Final de Gerência de Redes** | 2024
