<?php
require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();

// Inicializar o sistema
$sistema = inicializarSistema();

// Verificar se um ID de baralho foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$baralho_id = (int)$_GET['id'];

// Verificar se o baralho existe e pertence ao usuário
$baralho = $sistema['baralho']->obter($baralho_id);
if (!$baralho || !$sistema['baralho']->verificarProprietario($baralho_id, $usuario_id)) {
    header('Location: index.php');
    exit;
}

// Processar adição de cartão
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'adicionar_cartao' && isset($_POST['frente']) && isset($_POST['verso'])) {
        $resultado = $sistema['cartao']->criar($baralho_id, $_POST['frente'], $_POST['verso']);
        if ($resultado['sucesso']) {
            // Processar tags se existirem
            if (!empty($_POST['tags'])) {
                $tags = explode(',', $_POST['tags']);
                foreach ($tags as $tag_nome) {
                    $tag_nome = trim($tag_nome);
                    if (!empty($tag_nome)) {
                        $tag_id = $sistema['tag']->criar($tag_nome);
                        if ($tag_id) {
                            $sistema['tag']->atribuirAoCartao($resultado['id'], $tag_id);
                        }
                    }
                }
            }
            $mensagem = exibirAlerta('success', $resultado['mensagem']);
        } else {
            $mensagem = exibirAlerta('danger', $resultado['mensagem']);
        }
    } elseif ($_POST['acao'] === 'editar_cartao' && isset($_POST['cartao_id']) && isset($_POST['frente']) && isset($_POST['verso'])) {
        $resultado = $sistema['cartao']->atualizar($_POST['cartao_id'], $_POST['frente'], $_POST['verso']);
        if ($resultado['sucesso']) {
            $mensagem = exibirAlerta('success', $resultado['mensagem']);
        } else {
            $mensagem = exibirAlerta('danger', $resultado['mensagem']);
        }
    } elseif ($_POST['acao'] === 'excluir_cartao' && isset($_POST['cartao_id'])) {
        $resultado = $sistema['cartao']->excluir($_POST['cartao_id']);
        if ($resultado['sucesso']) {
            $mensagem = exibirAlerta('success', $resultado['mensagem']);
        } else {
            $mensagem = exibirAlerta('danger', $resultado['mensagem']);
        }
    } elseif ($_POST['acao'] === 'editar_baralho' && isset($_POST['nome']) && isset($_POST['descricao'])) {
        $resultado = $sistema['baralho']->atualizar($baralho_id, $_POST['nome'], $_POST['descricao']);
        if ($resultado['sucesso']) {
            $baralho = $sistema['baralho']->obter($baralho_id); // Atualizar dados do baralho
            $mensagem = exibirAlerta('success', $resultado['mensagem']);
        } else {
            $mensagem = exibirAlerta('danger', $resultado['mensagem']);
        }
    }
}

// Obter lista de cartões
$cartoes = $sistema['cartao']->listar($baralho_id);

// Contar cartões para revisão
$cartoes_para_revisar = $sistema['cartao']->contarParaRevisar($baralho_id);
?>

<html lang="pt-br" <?php echo obterAtributoTemaEscuro(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baralho: <?php echo htmlspecialchars($baralho['nome']); ?> - Sistema de Flashcards</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href= CSS/baralho.css>
  
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">Sistema de Flashcards</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Meus Baralhos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="estudar.php">Estudar</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php">Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo $mensagem; ?>

        <div class="row">
            <div class="col-md-8">
                <h1><?php echo htmlspecialchars($baralho['nome']); ?></h1>
                <p class="text-muted"><?php echo nl2br(htmlspecialchars($baralho['descricao'])); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editarBaralhoModal">
                    <i class="fas fa-edit"></i> Editar Baralho
                </button>
                <a href="estudar.php?baralho=<?php echo $baralho_id; ?>" class="btn btn-success">
                    <i class="fas fa-book-reader"></i> Estudar
                    <?php if ($cartoes_para_revisar > 0): ?>
                        <span class="badge bg-danger"><?php echo $cartoes_para_revisar; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Cartões (<?php echo count($cartoes); ?>)</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adicionarCartaoModal">
                            <i class="fas fa-plus"></i> Adicionar Cartão
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($cartoes)): ?>
                            <div class="alert alert-info">
                                Este baralho ainda não possui cartões. Clique em "Adicionar Cartão" para começar.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($cartoes as $cartao): ?>
                                    <div class="col-md-4">
                                        <div class="card-container">
                                            <div class="flashcard" onclick="this.classList.toggle('flipped')">
                                                <div class="card-front">
                                                    <?php echo nl2br(htmlspecialchars($cartao['frente'])); ?>
                                                </div>
                                                <div class="card-back">
                                                    <?php echo nl2br(htmlspecialchars($cartao['verso'])); ?>
                                                </div>
                                                <div class="card-actions">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); editarCartao(<?php echo $cartao['id']; ?>, '<?php echo addslashes($cartao['frente']); ?>', '<?php echo addslashes($cartao['verso']); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); excluirCartao(<?php echo $cartao['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="card-tags">
                                                    <?php
                                                    $tags = $sistema['tag']->listarPorCartao($cartao['id']);
                                                    foreach ($tags as $tag): ?>
                                                        <span class="badge bg-secondary tag-badge"><?php echo htmlspecialchars($tag['nome']); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar Cartão -->
    <div class="modal fade" id="adicionarCartaoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Novo Cartão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="acao" value="adicionar_cartao">
                        <div class="mb-3">
                            <label for="frente" class="form-label">Frente</label>
                            <textarea class="form-control" id="frente" name="frente" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="verso" class="form-label">Verso</label>
                            <textarea class="form-control" id="verso" name="verso" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags (separadas por vírgula)</label>
                            <input type="text" class="form-control" id="tags" name="tags" placeholder="Ex: importante, difícil, revisão">
                        </div>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Cartão -->
    <div class="modal fade" id="editarCartaoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Cartão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="formEditarCartao">
                        <input type="hidden" name="acao" value="editar_cartao">
                        <input type="hidden" name="cartao_id" id="editar_cartao_id">
                        <div class="mb-3">
                            <label for="editar_frente" class="form-label">Frente</label>
                            <textarea class="form-control" id="editar_frente" name="frente" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editar_verso" class="form-label">Verso</label>
                            <textarea class="form-control" id="editar_verso" name="verso" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Atualizar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Excluir Cartão -->
    <div class="modal fade" id="excluirCartaoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir este cartão? Esta ação não pode ser desfeita.</p>
                    <form method="post" id="formExcluirCartao">
                        <input type="hidden" name="acao" value="excluir_cartao">
                        <input type="hidden" name="cartao_id" id="excluir_cartao_id">
                        <button type="submit" class="btn btn-danger">Excluir</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Baralho -->
    <div class="modal fade" id="editarBaralhoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Baralho</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="acao" value="editar_baralho">
                        <div class="mb-3">
                            <label for="nome_baralho" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome_baralho" name="nome" value="<?php echo htmlspecialchars($baralho['nome']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="descricao_baralho" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao_baralho" name="descricao" rows="3"><?php echo htmlspecialchars($baralho['descricao']); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Atualizar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editarCartao(id, frente, verso) {
            document.getElementById('editar_cartao_id').value = id;
            document.getElementById('editar_frente').value = frente;
            document.getElementById('editar_verso').value = verso;
            new bootstrap.Modal(document.getElementById('editarCartaoModal')).show();
        }
        
        function excluirCartao(id) {
            document.getElementById('excluir_cartao_id').value = id;
            new bootstrap.Modal(document.getElementById('excluirCartaoModal')).show();
        }
    </script>
</body>
</html>