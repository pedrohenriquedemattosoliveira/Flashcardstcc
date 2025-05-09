<?php
require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();
$sistema = inicializarSistema();
verificarTemaEscuro();

// Processar exclusão de baralho
$mensagem = '';
$tipo_alerta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_baralho'])) {
    $baralho_id = (int)$_POST['baralho_id'];
    
    // Verificar se o baralho pertence ao usuário
    if ($sistema['baralho']->verificarProprietario($baralho_id, $usuario_id)) {
        $resultado = $sistema['baralho']->excluir($baralho_id);
        $mensagem = $resultado['mensagem'];
        $tipo_alerta = $resultado['sucesso'] ? 'success' : 'danger';
    } else {
        $mensagem = 'Você não tem permissão para excluir este baralho';
        $tipo_alerta = 'danger';
    }
}

// Obter todos os baralhos do usuário
$baralhos = $sistema['baralho']->listar($usuario_id);

// Incluir o cabeçalho
$titulo_pagina = "Meus Baralhos";
include 'includes/header.php';
?>

<div class="container mt-4">
    <?php if (!empty($mensagem)): ?>
        <?php echo exibirAlerta($tipo_alerta, $mensagem); ?>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Meus Baralhos</h1>
        <a href="criar_baralho.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-2"></i>Novo Baralho
        </a>
    </div>
    
    <!-- Filtros e busca -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" name="busca" placeholder="Buscar baralhos..." 
                               value="<?php echo isset($_GET['busca']) ? htmlspecialchars($_GET['busca']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="ordernar">
                        <option value="recentes" <?php echo (!isset($_GET['ordernar']) || $_GET['ordernar'] == 'recentes') ? 'selected' : ''; ?>>Mais recentes</option>
                        <option value="antigos" <?php echo (isset($_GET['ordernar']) && $_GET['ordernar'] == 'antigos') ? 'selected' : ''; ?>>Mais antigos</option>
                        <option value="nome_asc" <?php echo (isset($_GET['ordernar']) && $_GET['ordernar'] == 'nome_asc') ? 'selected' : ''; ?>>Nome (A-Z)</option>
                        <option value="nome_desc" <?php echo (isset($_GET['ordernar']) && $_GET['ordernar'] == 'nome_desc') ? 'selected' : ''; ?>>Nome (Z-A)</option>
                        <option value="mais_cartoes" <?php echo (isset($_GET['ordernar']) && $_GET['ordernar'] == 'mais_cartoes') ? 'selected' : ''; ?>>Mais cartões</option>
                        <option value="pendentes" <?php echo (isset($_GET['ordernar']) && $_GET['ordernar'] == 'pendentes') ? 'selected' : ''; ?>>Pendentes de revisão</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Aplicar</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (empty($baralhos)): ?>
        <div class="alert alert-info" role="alert">
            <h4 class="alert-heading mb-3">Nenhum baralho encontrado!</h4>
            <p>Você ainda não criou nenhum baralho. Os baralhos são conjuntos de cartões de memória que você pode usar para estudar.</p>
            <hr>
            <p class="mb-0">Crie seu primeiro baralho clicando no botão "Novo Baralho" acima.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($baralhos as $baralho): ?>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h5 class="card-title text-truncate mb-0">
                                <?php echo htmlspecialchars($baralho['nome']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($baralho['descricao'])): ?>
                                <p class="card-text mb-3"><?php echo nl2br(htmlspecialchars($baralho['descricao'])); ?></p>
                            <?php else: ?>
                                <p class="card-text text-muted mb-3"><em>Sem descrição</em></p>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary rounded-pill">
                                    <i class="fas fa-clone me-1"></i><?php echo $baralho['total_cartoes']; ?> cartões
                                </span>
                                <span class="text-muted small">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($baralho['data_criacao'])); ?>
                                </span>
                            </div>
                            
                            <?php if ($baralho['cartoes_para_revisar'] > 0): ?>
                                <div class="alert alert-warning py-2 mb-3">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    <strong><?php echo $baralho['cartoes_para_revisar']; ?></strong> cartões para revisar hoje
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <a href="baralho.php?id=<?php echo $baralho['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="fas fa-eye me-1"></i>Ver
                                    </a>
                                    <a href="editar_baralho.php?id=<?php echo $baralho['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-edit me-1"></i>Editar
                                    </a>
                                </div>
                                <div>
                                    <?php if ($baralho['total_cartoes'] > 0): ?>
                                        <a href="estudar.php?baralho_id=<?php echo $baralho['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="fas fa-graduation-cap me-1"></i>Estudar
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success" disabled>
                                            <i class="fas fa-graduation-cap me-1"></i>Estudar
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-danger ms-1" 
                                            data-bs-toggle="modal" data-bs-target="#excluirBaralhoModal<?php echo $baralho['id']; ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal de Exclusão para cada baralho -->
                <div class="modal fade" id="excluirBaralhoModal<?php echo $baralho['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">Excluir Baralho</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <p>Tem certeza que deseja excluir o baralho <strong><?php echo htmlspecialchars($baralho['nome']); ?></strong>?</p>
                                <p class="text-danger"><strong>Atenção:</strong> Esta ação excluirá todos os cartões associados a este baralho e não pode ser desfeita!</p>
                            </div>
                            <div class="modal-footer">
                                <form method="post" action="">
                                    <input type="hidden" name="baralho_id" value="<?php echo $baralho['id']; ?>">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" name="excluir_baralho" class="btn btn-danger">Excluir</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Botão Flutuante para Criar Novo Baralho -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 5">
    <a href="criar_baralho.php" class="btn btn-lg btn-primary rounded-circle shadow-sm" data-bs-toggle="tooltip" title="Criar Novo Baralho">
        <i class="fas fa-plus"></i>
    </a>
</div>

<?php include 'includes/footer.php'; ?>