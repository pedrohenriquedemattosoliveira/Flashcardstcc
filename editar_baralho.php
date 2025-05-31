<?php
require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();
$sistema = inicializarSistema();
verificarTemaEscuro();

// Verificar se o ID do baralho foi passado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: baralhos.php');
    exit;
}

$baralho_id = (int)$_GET['id'];

// Verificar se o baralho existe e pertence ao usuário
if (!$sistema['baralho']->verificarProprietario($baralho_id, $usuario_id)) {
    $_SESSION['mensagem'] = 'Você não tem permissão para editar este baralho';
    $_SESSION['tipo_alerta'] = 'danger';
    header('Location: baralhos.php');
    exit;
}

// Obter dados do baralho
$baralho = $sistema['baralho']->obter($baralho_id);
if (!$baralho) {
    $_SESSION['mensagem'] = 'Baralho não encontrado';
    $_SESSION['tipo_alerta'] = 'danger';
    header('Location: baralhos.php');
    exit;
}

// Processar formulário de remoção
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_baralho'])) {
    $confirmar_nome = trim($_POST['confirmar_nome'] ?? '');
    
    if ($confirmar_nome === $baralho['nome']) {
        $resultado = $sistema['baralho']->remover($baralho_id);
        
        if ($resultado['sucesso']) {
            $_SESSION['mensagem'] = 'Baralho removido com sucesso';
            $_SESSION['tipo_alerta'] = 'success';
            header('Location: baralhos.php');
            exit;
        } else {
            $mensagem = $resultado['mensagem'];
            $tipo_alerta = 'danger';
        }
    } else {
        $mensagem = 'Nome do baralho não confere. Digite exatamente: ' . $baralho['nome'];
        $tipo_alerta = 'danger';
    }
}

// Processar formulário de edição
$mensagem = '';
$tipo_alerta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_baralho'])) {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao'] ?? '');
    
    // Validação básica
    if (empty($nome)) {
        $mensagem = 'O nome do baralho é obrigatório';
        $tipo_alerta = 'danger';
    } else {
        $resultado = $sistema['baralho']->atualizar($baralho_id, $nome, $descricao);
        
        if ($resultado['sucesso']) {
            $mensagem = $resultado['mensagem'];
            $tipo_alerta = 'success';
            // Atualizar dados do baralho na página
            $baralho['nome'] = $nome;
            $baralho['descricao'] = $descricao;
        } else {
            $mensagem = $resultado['mensagem'];
            $tipo_alerta = 'danger';
        }
    }
}

// Incluir o cabeçalho
$titulo_pagina = "Editar Baralho";
include 'includes/header.php';
?>

<div class="container mt-4">
    <?php if (!empty($mensagem)): ?>
        <?php echo exibirAlerta($tipo_alerta, $mensagem); ?>
    <?php endif; ?>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="h4 mb-0">Editar Baralho</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Baralho*</label>
                            <input type="text" class="form-control" id="nome" name="nome" required 
                                   value="<?php echo htmlspecialchars($baralho['nome']); ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label for="descricao" class="form-label">Descrição (opcional)</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="4"><?php echo htmlspecialchars($baralho['descricao']); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="baralho.php?id=<?php echo $baralho_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Voltar
                            </a>
                            <button type="submit" name="editar_baralho" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h3 class="h5 mb-0">Informações do Baralho</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Data de criação:</strong> <?php echo date('d/m/Y', strtotime($baralho['data_criacao'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php 
                            // Obter contagem de cartões
                            $total_cartoes = $sistema['cartao']->contarPorBaralho($baralho_id);
                            $cartoes_para_revisar = $sistema['cartao']->contarParaRevisar($baralho_id);
                            ?>
                            <p><strong>Total de cartões:</strong> <?php echo $total_cartoes; ?></p>
                            <?php if ($cartoes_para_revisar > 0): ?>
                                <p><strong>Cartões para revisar:</strong> <?php echo $cartoes_para_revisar; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="d-flex justify-content-between">
                            <a href="baralho.php?id=<?php echo $baralho_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>Ver Cartões
                            </a>
                            <?php if ($total_cartoes > 0): ?>
                                <a href="estudar.php?baralho_id=<?php echo $baralho_id; ?>" class="btn btn-success">
                                    <i class="fas fa-graduation-cap me-1"></i>Estudar Este Baralho
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card para remover baralho -->
            <div class="card mt-4 border-danger">
                <div class="card-header bg-danger text-white">
                    <h3 class="h5 mb-0">Zona de Perigo</h3>
                </div>
                <div class="card-body">
                    <h5 class="text-danger">Remover Baralho</h5>
                    <p class="text-muted mb-3">
                        <strong>Atenção:</strong> Esta ação é irreversível. Ao remover o baralho, todos os cartões 
                        e dados de progresso associados também serão permanentemente excluídos.
                    </p>
                    
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRemoverBaralho">
                        <i class="fas fa-trash me-2"></i>Remover Baralho
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmação para remover baralho -->
<div class="modal fade" id="modalRemoverBaralho" tabindex="-1" aria-labelledby="modalRemoverBaralhoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalRemoverBaralhoLabel">Confirmar Remoção</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Esta ação não pode ser desfeita!</strong>
                    </div>
                    
                    <p>Você está prestes a remover permanentemente o baralho:</p>
                    <p><strong>"<?php echo htmlspecialchars($baralho['nome']); ?>"</strong></p>
                    
                    <?php if ($total_cartoes > 0): ?>
                        <p class="text-danger">
                            <i class="fas fa-info-circle me-1"></i>
                            Este baralho contém <?php echo $total_cartoes; ?> cartão(ões) que também serão removidos.
                        </p>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <label for="confirmar_nome" class="form-label">
                            Para confirmar, digite o nome exato do baralho:
                        </label>
                        <input type="text" class="form-control" id="confirmar_nome" name="confirmar_nome" 
                               placeholder="<?php echo htmlspecialchars($baralho['nome']); ?>" required>
                        <small class="form-text text-muted">Digite: <?php echo htmlspecialchars($baralho['nome']); ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="remover_baralho" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Sim, Remover Permanentemente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Validação adicional no frontend
document.getElementById('modalRemoverBaralho').addEventListener('show.bs.modal', function () {
    document.getElementById('confirmar_nome').value = '';
});

// Habilitar/desabilitar botão de confirmação baseado no nome digitado
document.getElementById('confirmar_nome').addEventListener('input', function() {
    const nomeBaralho = <?php echo json_encode($baralho['nome']); ?>;
    const botaoRemover = document.querySelector('button[name="remover_baralho"]');
    
    if (this.value === nomeBaralho) {
        botaoRemover.disabled = false;
        botaoRemover.classList.remove('btn-secondary');
        botaoRemover.classList.add('btn-danger');
    } else {
        botaoRemover.disabled = true;
        botaoRemover.classList.remove('btn-danger');
        botaoRemover.classList.add('btn-secondary');
    }
});

// Inicializar com botão desabilitado
document.addEventListener('DOMContentLoaded', function() {
    const botaoRemover = document.querySelector('button[name="remover_baralho"]');
    botaoRemover.disabled = true;
    botaoRemover.classList.add('btn-secondary');
    botaoRemover.classList.remove('btn-danger');
});
</script>

<?php include 'includes/footer.php'; ?>