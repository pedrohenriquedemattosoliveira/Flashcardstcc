<?php
require_once 'config.php';
session_start();

// Se já estiver logado, redireciona para a página inicial
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Inicializar sistema
$sistema = inicializarSistema();
$mensagem = '';

// Processar o formulário de cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    // Validações básicas
    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
        $mensagem = exibirAlerta('danger', 'Todos os campos são obrigatórios.');
    } elseif ($senha !== $confirmar_senha) {
        $mensagem = exibirAlerta('danger', 'As senhas não coincidem.');
    } elseif (strlen($senha) < 6) {
        $mensagem = exibirAlerta('danger', 'A senha deve ter pelo menos 6 caracteres.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = exibirAlerta('danger', 'E-mail inválido.');
    } else {
        // Tenta cadastrar o usuário
        $resultado = $sistema['usuario']->cadastrar($nome, $email, $senha);
        
        if ($resultado['sucesso']) {
            $mensagem = exibirAlerta('success', 'Cadastro realizado com sucesso! Você já pode fazer login.');
            // Opcional: redirecionar para a página de login após alguns segundos
            header("refresh:3;url=login.php");
        } else {
            $mensagem = exibirAlerta('danger', $resultado['mensagem']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Sistema de Flashcards</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>Cadastro - Sistema de Flashcards</h4>
                    </div>
                    <div class="card-body">
                        <?php echo $mensagem; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="senha" class="form-label">Senha</label>
                                <input type="password" class="form-control" id="senha" name="senha" required>
                                <div class="form-text">A senha deve ter pelo menos 6 caracteres.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmar_senha" class="form-label">Confirme a senha</label>
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Cadastrar</button>
                                <a href="login.php" class="btn btn-outline-secondary">Voltar para login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>