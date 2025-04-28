<?php
require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();
$sistema = inicializarSistema();

// Processar formulário de atualização de perfil
$mensagem = '';
$tipo_alerta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar qual formulário foi enviado
    if (isset($_POST['atualizar_perfil'])) {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha_atual = $_POST['senha_atual'] ?? '';
        $nova_senha = $_POST['nova_senha'] ?? '';
        $confirmar_senha = $_POST['confirmar_senha'] ?? '';
        
        // Validações básicas
        if (empty($nome) || empty($email)) {
            $mensagem = 'Nome e e-mail são obrigatórios';
            $tipo_alerta = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'E-mail inválido';
            $tipo_alerta = 'danger';
        } else {
            // Se senha atual foi fornecida, valida e atualiza senha
            if (!empty($senha_atual)) {
                // Verificar se a senha atual está correta
                $stmt = $sistema['usuario']->db->prepare("SELECT senha FROM usuarios WHERE id = ?");
                $stmt->execute([$usuario_id]);
                $usuario_senha = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($senha_atual, $usuario_senha['senha'])) {
                    $mensagem = 'Senha atual incorreta';
                    $tipo_alerta = 'danger';
                } elseif (empty($nova_senha) || $nova_senha != $confirmar_senha) {
                    $mensagem = 'Nova senha e confirmação não conferem';
                    $tipo_alerta = 'danger';
                } else {
                    // Atualiza perfil com nova senha
                    $resultado = $sistema['usuario']->atualizarPerfil($usuario_id, $nome, $email, $nova_senha);
                    $mensagem = $resultado['mensagem'];
                    $tipo_alerta = $resultado['sucesso'] ? 'success' : 'danger';
                }
            } else {
                // Atualiza perfil sem alterar senha
                $resultado = $sistema['usuario']->atualizarPerfil($usuario_id, $nome, $email);
                $mensagem = $resultado['mensagem'];
                $tipo_alerta = $resultado['sucesso'] ? 'success' : 'danger';
            }
        }
    }
    
    // Se for a exclusão da conta
    if (isset($_POST['excluir_conta']) && isset($_POST['confirmar_exclusao'])) {
        // Implemente a lógica de exclusão de conta
        try {
            $db = new Database($config);
            $conn = $db->getConnection();
            
            // Iniciar transação
            $conn->beginTransaction();
            
            // Deletar o usuário (as restrições de chave estrangeira cuidarão de deletar os dados relacionados)
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            
            $conn->commit();
            
            // Destruir a sessão
            session_destroy();
            
            // Redirecionar para a página de login
            header('Location: login.php?msg=conta_excluida');
            exit;
        } catch (Exception $e) {
            if ($conn) {
                $conn->rollBack();
            }
            $mensagem = 'Erro ao excluir conta: ' . $e->getMessage();
            $tipo_alerta = 'danger';
        }
    }
}

// Obter dados do usuário
$usuario = $sistema['usuario']->obterPorId($usuario_id);

// Obter estatísticas do usuário
$db = new Database($config);
$conn = $db->getConnection();

// Total de baralhos
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM baralhos WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total_baralhos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de cartões
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM cartoes c
    JOIN baralhos b ON c.baralho_id = b.id
    WHERE b.usuario_id = ?
");
$stmt->execute([$usuario_id]);
$total_cartoes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Cartões para revisar hoje
$hoje = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM cartoes c
    JOIN baralhos b ON c.baralho_id = b.id
    JOIN estatisticas e ON c.id = e.cartao_id
    WHERE b.usuario_id = ? AND e.proxima_revisao <= ?
");
$stmt->execute([$usuario_id, $hoje]);
$cartoes_para_revisar = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Cartões estudados no último mês
$ultimo_mes = date('Y-m-d', strtotime('-30 days'));
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT c.id) as total 
    FROM cartoes c
    JOIN baralhos b ON c.baralho_id = b.id
    JOIN estatisticas e ON c.id = e.cartao_id
    WHERE b.usuario_id = ? AND e.ultima_revisao >= ?
");
$stmt->execute([$usuario_id, $ultimo_mes]);
$cartoes_estudados_mes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Atividade recente (últimos 10 baralhos acessados, por exemplo)
$stmt = $conn->prepare("
    SELECT b.*, 
           (SELECT COUNT(*) FROM cartoes WHERE baralho_id = b.id) AS total_cartoes
    FROM baralhos b 
    WHERE b.usuario_id = ? 
    ORDER BY b.data_criacao DESC
    LIMIT 5
");
$stmt->execute([$usuario_id]);
$baralhos_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Incluir o cabeçalho
$titulo_pagina = "Meu Perfil";
include 'includes/header.php';
?>

<div class="container mt-4">
    <?php if (!empty($mensagem)): ?>
        <?php echo exibirAlerta($tipo_alerta, $mensagem); ?>
    <?php endif; ?>
    
    <h1 class="mb-4">Meu Perfil</h1>
    
    <div class="row">
        <!-- Coluna de estatísticas -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Estatísticas</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total de baralhos
                            <span class="badge bg-primary rounded-pill"><?php echo $total_baralhos; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total de cartões
                            <span class="badge bg-primary rounded-pill"><?php echo $total_cartoes; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Cartões para revisar hoje
                            <span class="badge bg-warning text-dark rounded-pill"><?php echo $cartoes_para_revisar; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Cartões estudados no último mês
                            <span class="badge bg-success rounded-pill"><?php echo $cartoes_estudados_mes; ?></span>
                        </li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a href="dashboard.php" class="btn btn-sm btn-primary">Ver Dashboard</a>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">Baralhos Recentes</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (empty($baralhos_recentes)): ?>
                            <li class="list-group-item">Nenhum baralho criado ainda.</li>
                        <?php else: ?>
                            <?php foreach ($baralhos_recentes as $baralho): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="baralho.php?id=<?php echo $baralho['id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($baralho['nome']); ?>
                                    </a>
                                    <span class="badge bg-secondary rounded-pill"><?php echo $baralho['total_cartoes']; ?> cartões</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card-footer">
                    <a href="baralhos.php" class="btn btn-sm btn-success">Ver Todos</a>
                </div>
            </div>
        </div>
        
        <!-- Coluna de informações do perfil -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">Informações de Perfil</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="data_cadastro" class="form-label">Data de Cadastro</label>
                            <input type="text" class="form-control" id="data_cadastro" value="<?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?>" readonly>
                        </div>
                        
                        <div class="mt-4">
                            <h5>Alterar Senha</h5>
                            <div class="mb-3">
                                <label for="senha_atual" class="form-label">Senha Atual</label>
                                <input type="password" class="form-control" id="senha_atual" name="senha_atual">
                                <div class="form-text">Preencha apenas se desejar alterar sua senha.</div>
                            </div>
                            <div class="mb-3">
                                <label for="nova_senha" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha">
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="atualizar_perfil" class="btn btn-primary">Atualizar Perfil</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Perigo</h5>
                </div>
                <div class="card-body">
                    <h5 class="card-title">Excluir Conta</h5>
                    <p class="card-text">Esta ação não pode ser desfeita. Todos os seus baralhos, cartões e estatísticas serão permanentemente removidos.</p>
                    
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#excluirContaModal">
                        Excluir Minha Conta
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão de Conta -->
<div class="modal fade" id="excluirContaModal" tabindex="-1" aria-labelledby="excluirContaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="excluirContaModalLabel">Confirmação de Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold text-danger">ATENÇÃO: Esta ação não pode ser desfeita!</p>
                <p>Todos os seus dados serão permanentemente excluídos, incluindo:</p>
                <ul>
                    <li>Todos os seus baralhos</li>
                    <li>Todos os seus cartões</li>
                    <li>Todo o seu histórico de estudos</li>
                </ul>
                <p>Tem certeza que deseja continuar?</p>
                
                <form method="post" action="">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirmar_exclusao" id="confirmar_exclusao" required>
                        <label class="form-check-label" for="confirmar_exclusao">
                            Sim, estou ciente e desejo excluir permanentemente minha conta
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="excluir_conta" class="btn btn-danger">Excluir Minha Conta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>