<?php
require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();

// Inicializar o sistema
$sistema = inicializarSistema();

verificarTemaEscuro();

// Obter os baralhos do usuário
$baralhos = $sistema['baralho']->listar($usuario_id);

// Contadores
$total_baralhos = count($baralhos);
$total_cartoes = 0;
$cartoes_para_revisar = 0;

foreach ($baralhos as $baralho) {
    $total_cartoes += $baralho['total_cartoes'];
    $cartoes_para_revisar += $baralho['cartoes_para_revisar'];
}
?>
<html lang="pt-br" <?php echo obterAtributoTemaEscuro(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corvus Cards - <?php echo $titulo; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Flashcards</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/index.css">

</head>
<body>
    <!-- Barra de navegação -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12 mb-4">
                <h2>Painel do Estudante</h2>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-primary stats-card">
                    <div class="card-body">
                        <h5 class="card-title">Total de Baralhos</h5>
                        <p class="card-text display-4"><?php echo $total_baralhos; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-success stats-card">
                    <div class="card-body">
                        <h5 class="card-title">Total de Cartões</h5>
                        <p class="card-text display-4"><?php echo $total_cartoes; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-danger stats-card">
                    <div class="card-body">
                        <h5 class="card-title">Cartões para Revisar</h5>
                        <p class="card-text display-4"><?php echo $cartoes_para_revisar; ?></p>
                        <?php if ($cartoes_para_revisar > 0): ?>
                        <a href="estudar.php" class="btn btn-light btn-sm">Revisar Agora</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Baralhos -->
        <div class="row mb-4">
            <div class="col-md-12 d-flex justify-content-between align-items-center">
                <h3>Meus Baralhos</h3>
                <a href="novo_baralho.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Novo Baralho
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?php if (empty($baralhos)): ?>
                <div class="alert alert-info">
                    Você ainda não possui baralhos. <a href="novo_baralho.php">Crie seu primeiro baralho</a>.
                </div>
                <?php else: ?>
                <div class="card-deck">
                    <?php foreach ($baralhos as $baralho): ?>
                    <div class="card deck-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($baralho['nome']); ?></h5>
                            <p class="card-text text-muted small"><?php echo htmlspecialchars($baralho['descricao']); ?></p>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge bg-primary"><?php echo $baralho['total_cartoes']; ?> cartões</span>
                                <span class="badge bg-danger"><?php echo $baralho['cartoes_para_revisar']; ?> para revisar</span>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <?php if ($baralho['cartoes_para_revisar'] > 0): ?>
                            <a href="estudar.php?baralho_id=<?php echo $baralho['id']; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-play"></i> Estudar
                            </a>
                            <?php else: ?>
                            <button class="btn btn-secondary btn-sm" disabled>
                                <i class="fas fa-check"></i> Em dia
                            </button>
                            <?php endif; ?>
                            <a href="editar_baralho.php?id=<?php echo $baralho['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-cog"></i> Gerenciar
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>