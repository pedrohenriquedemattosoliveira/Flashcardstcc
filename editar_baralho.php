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
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>