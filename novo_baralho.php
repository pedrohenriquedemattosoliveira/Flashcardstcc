<?php
require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();

// Inicializar sistema
$sistema = inicializarSistema();
$mensagem = '';

// Processar o formulário de criação de baralho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    
    // Validações básicas
    if (empty($nome)) {
        $mensagem = exibirAlerta('danger', 'O nome do baralho é obrigatório.');
    } else {
        // Tenta criar o baralho
        $resultado = $sistema['baralho']->criar($usuario_id, $nome, $descricao);
        
        if ($resultado['sucesso']) {
            $mensagem = exibirAlerta('success', 'Baralho criado com sucesso!');
            // Redirecionar para a página do baralho após alguns segundos
            $baralho_id = $resultado['id'];
            header("refresh:2;url=baralho.php?id={$baralho_id}");
        } else {
            $mensagem = exibirAlerta('danger', $resultado['mensagem']);
        }
    }
}

// Obter informações do usuário
$usuario = $sistema['usuario']->obterPorId($usuario_id);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Baralho - Sistema de Flashcards</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome para ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-layer-group me-2"></i>Sistema de Flashcards
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Início
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="estudar.php">
                            <i class="fas fa-graduation-cap"></i> Estudar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="novo_baralho.php">
                            <i class="fas fa-plus-circle"></i> Novo Baralho
                        </a>
                    </li>
                </ul>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($usuario['nome']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card me-2"></i>Meu Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Conteúdo Principal -->
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Criar Novo Baralho</h4>
                    </div>
                    <div class="card-body">
                        <?php echo $mensagem; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome do Baralho <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nome" name="nome" 
                                       value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" 
                                       placeholder="Ex: Vocabulário Inglês" required>
                                <div class="form-text">Escolha um nome descritivo para seu baralho.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="descricao" class="form-label">Descrição</label>
                                <textarea class="form-control" id="descricao" name="descricao" rows="4" 
                                          placeholder="Descreva o conteúdo e objetivos deste baralho..."><?php echo isset($_POST['descricao']) ? htmlspecialchars($_POST['descricao']) : ''; ?></textarea>
                                <div class="form-text">Uma boa descrição ajuda a lembrar o propósito do baralho.</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Criar Baralho
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Voltar
                                </a>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="small text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Depois de criar o baralho, você poderá adicionar cartões de estudo.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rodapé -->
    <footer class="bg-dark text-white text-center py-3 mt-5">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Sistema de Flashcards - Todos os direitos reservados</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>