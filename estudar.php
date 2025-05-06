<?php
require_once 'config.php';

// Verificar se o usuário está logado
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Inicializar o sistema
$db = new Database($config);
$conn = $db->getConnection();

// Inicializa os objetos
$baralho = new Baralho($conn);
$cartao = new Cartao($conn);
$repetidor = new RepetidorEspacado($conn);
$tag = new Tag($conn);

// Determinar o modo de estudo
$baralho_id = isset($_GET['baralho']) && is_numeric($_GET['baralho']) ? (int)$_GET['baralho'] : null;
$modo_estudo = 'todos'; // Padrão é estudar todos os cartões

// Se um baralho específico foi solicitado, verificar se pertence ao usuário
if ($baralho_id) {
    $deck = $baralho->obter($baralho_id);
    if (!$deck || !$baralho->verificarProprietario($baralho_id, $usuario_id)) {
        header('Location: index.php');
        exit;
    }
    $modo_estudo = 'baralho';
}

// Mensagem para feedback ao usuário
$mensagem = '';

// Processar resposta ao cartão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'responder' && isset($_POST['cartao_id']) && isset($_POST['qualidade'])) {
        $cartao_id = (int)$_POST['cartao_id'];
        $qualidade = (int)$_POST['qualidade'];
        
        $resultado = $repetidor->processarResposta($cartao_id, $qualidade);
        if ($resultado['sucesso']) {
            $mensagem = exibirAlerta('success', 'Resposta registrada! Próxima revisão em ' . $resultado['proximo_intervalo'] . ' dia(s).');
        } else {
            $mensagem = exibirAlerta('danger', $resultado['mensagem']);
        }
    }
}

// Obter cartões para estudar
$limite = 20; // Limitar quantidade de cartões por sessão

if ($baralho_id) {
    // Obter cartões do baralho específico
    $stmt = $conn->prepare("
        SELECT c.*, e.facilidade, e.intervalo, e.repeticoes, e.proxima_revisao, e.ultima_revisao 
        FROM cartoes c 
        JOIN estatisticas e ON c.id = e.cartao_id 
        WHERE c.baralho_id = ? 
        ORDER BY c.id DESC 
        LIMIT $limite
    ");
    $stmt->execute([$baralho_id]);
    $cartoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Obter cartões de todos os baralhos do usuário
    $stmt = $conn->prepare("
        SELECT c.*, e.facilidade, e.intervalo, e.repeticoes, e.proxima_revisao, e.ultima_revisao 
        FROM cartoes c 
        JOIN estatisticas e ON c.id = e.cartao_id 
        JOIN baralhos b ON c.baralho_id = b.id 
        WHERE b.usuario_id = ? 
        ORDER BY c.id DESC 
        LIMIT $limite
    ");
    $stmt->execute([$usuario_id]);
    $cartoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obter todos os baralhos do usuário para o menu de seleção
$baralhos = $baralho->listar($usuario_id);

// Verificar se existem cartões para estudar
$tem_cartoes = !empty($cartoes);
$cartao_atual = $tem_cartoes ? $cartoes[0] : null;

// Contar cartões para revisar em cada baralho
foreach ($baralhos as &$b) {
    $b['cartoes_para_revisar'] = $b['total_cartoes'];
}

$total_para_revisar = 0;
foreach ($baralhos as $b) {
    $total_para_revisar += $b['cartoes_para_revisar'];
}

// Título da página
$titulo = "Estudar";
if ($baralho_id && isset($deck)) {
    $titulo .= " - " . htmlspecialchars($deck['nome']);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?> - Sistema de Flashcards</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .study-card {
            min-height: 300px;
            perspective: 1000px;
            margin-bottom: 20px;
        }
        .flashcard {
            width: 100%;
            height: 100%;
            position: relative;
            transition: transform 0.6s;
            transform-style: preserve-3d;
            cursor: pointer;
        }
        .flashcard.flipped {
            transform: rotateY(180deg);
        }
        .card-front, .card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            overflow-y: auto;
        }
        .card-front {
            background-color: #f8f9fa;
            border-left: 5px solid #007bff;
            z-index: 2;
        }
        .card-back {
            background-color: #e9ecef;
            border-left: 5px solid #28a745;
            transform: rotateY(180deg);
        }
        .card-content {
            font-size: 1.25rem;
            text-align: center;
            width: 100%;
        }
        .progress-container {
            margin-bottom: 20px;
        }
        .difficulty-buttons {
            margin-top: 20px;
            display: none;
        }
        .difficulty-buttons.visible {
            display: block;
        }
        .tag-badge {
            margin-right: 5px;
            font-size: 0.8rem;
        }
        .card-tags {
            position: absolute;
            bottom: 10px;
            left: 20px;
            z-index: 3;
        }
        .card-info {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        .btn-difficulty-0 { background-color: #dc3545; }
        .btn-difficulty-1 { background-color: #fd7e14; }
        .btn-difficulty-2 { background-color: #ffc107; }
        .btn-difficulty-3 { background-color: #20c997; }
        .btn-difficulty-4 { background-color: #0dcaf0; }
        .btn-difficulty-5 { background-color: #198754; }
    </style>
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
                        <a class="nav-link active" href="estudar.php">Estudar
                            <?php if ($total_para_revisar > 0): ?>
                                <span class="badge bg-danger"><?php echo $total_para_revisar; ?></span>
                            <?php endif; ?>
                        </a>
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

        <div class="row mb-4">
            <div class="col-md-8">
                <h1><?php echo $titulo; ?></h1>
            </div>
            <div class="col-md-4">
                <div class="input-group">
                    <label class="input-group-text" for="selecionarBaralho">Selecionar Baralho:</label>
                    <select class="form-select" id="selecionarBaralho" onchange="window.location.href='estudar.php' + (this.value ? '?baralho=' + this.value : '')">
                        <option value="">Todos os Baralhos</option>
                        <?php foreach ($baralhos as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $baralho_id == $b['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['nome']); ?> 
                                (<?php echo $b['total_cartoes']; ?> cartões)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($tem_cartoes): ?>
            <div class="row">
                <div class="col-12">
                    <div class="progress-container">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Cartões para revisar: <?php echo count($cartoes); ?></span>
                            <span id="progresso-texto">Cartão 1 de <?php echo count($cartoes); ?></span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" id="progresso-barra" role="progressbar" style="width: <?php echo (1/count($cartoes)) * 100; ?>%" 
                                aria-valuenow="1" aria-valuemin="0" aria-valuemax="<?php echo count($cartoes); ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="study-card">
                        <div class="flashcard" id="flashcard-atual" onclick="virarCartao()">
                            <div class="card-front">
                                <div class="card-content" id="card-frente">
                                    <?php echo nl2br(htmlspecialchars($cartao_atual['frente'])); ?>
                                </div>
                                <div class="card-info">
                                    Baralho: <?php 
                                        $nome_baralho = '';
                                        foreach ($baralhos as $b) {
                                            if ($b['id'] == $cartao_atual['baralho_id']) {
                                                $nome_baralho = $b['nome'];
                                                break;
                                            }
                                        }
                                        echo htmlspecialchars($nome_baralho);
                                    ?>
                                </div>
                                <div class="card-tags">
                                    <?php
                                    $tags = $tag->listarPorCartao($cartao_atual['id']);
                                    if ($tags):
                                        foreach ($tags as $t): ?>
                                            <span class="badge bg-secondary tag-badge"><?php echo htmlspecialchars($t['nome']); ?></span>
                                        <?php endforeach;
                                    endif; ?>
                                </div>
                            </div>
                            <div class="card-back">
                                <div class="card-content" id="card-verso">
                                    <?php echo nl2br(htmlspecialchars($cartao_atual['verso'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center mb-4">
                        <button class="btn btn-outline-secondary" onclick="virarCartao()">
                            <i class="fas fa-sync-alt"></i> Virar Cartão
                        </button>
                    </div>

                    <div class="difficulty-buttons" id="dificuldade-botoes">
                        <h5 class="text-center mb-3">Como foi sua lembrança?</h5>
                        <div class="d-flex justify-content-between">
                            <button class="btn text-white btn-difficulty-0" onclick="responderCartao(0)">
                                Não lembrei<br>
                                <small>Resetar cartão</small>
                            </button>
                            <button class="btn text-white btn-difficulty-1" onclick="responderCartao(1)">
                                Difícil<br>
                                <small>Revisar logo</small>
                            </button>
                            <button class="btn text-white btn-difficulty-2" onclick="responderCartao(2)">
                                Hesitei<br>
                                <small>Intervalo curto</small>
                            </button>
                            <button class="btn text-white btn-difficulty-3" onclick="responderCartao(3)">
                                Bom<br>
                                <small>Intervalo normal</small>
                            </button>
                            <button class="btn text-white btn-difficulty-4" onclick="responderCartao(4)">
                                Fácil<br>
                                <small>Intervalo longo</small>
                            </button>
                            <button class="btn text-white btn-difficulty-5" onclick="responderCartao(5)">
                                Perfeito<br>
                                <small>Intervalo muito longo</small>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulário para enviar resposta -->
            <form id="form-resposta" method="post" style="display: none;">
                <input type="hidden" name="acao" value="responder">
                <input type="hidden" name="cartao_id" id="cartao_id" value="<?php echo $cartao_atual['id']; ?>">
                <input type="hidden" name="qualidade" id="qualidade" value="">
            </form>

            <!-- JSON de dados dos cartões -->
            <script>
                const cartoes = <?php echo json_encode($cartoes); ?>;
                let cartaoAtual = 0;
                let totalCartoes = cartoes.length;
            </script>

        <?php else: ?>
            <div class="alert alert-info">
                <p>Não há cartões para estudar neste momento.</p>
                <?php if ($modo_estudo === 'baralho'): ?>
                    <p>Este baralho não possui cartões. Adicione alguns cartões para começar a estudar.</p>
                    <p><a href="baralho.php?id=<?php echo $baralho_id; ?>" class="btn btn-primary">Voltar para o Baralho</a></p>
                <?php else: ?>
                    <p>Crie baralhos e adicione cartões para começar seus estudos.</p>
                    <p><a href="index.php" class="btn btn-primary">Voltar para Meus Baralhos</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Conclusão -->
    <div class="modal fade" id="modalConcluido" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Estudo Concluído!</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Parabéns!</h4>
                        <p>Você concluiu todos os cartões programados para revisão hoje.</p>
                    </div>
                    
                    <div class="d-flex justify-content-around">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Página Inicial
                        </a>
                        <a href="estudar.php<?php echo $baralho_id ? "?baralho=$baralho_id" : ''; ?>" class="btn btn-success">
                            <i class="fas fa-sync-alt"></i> Estudar Novamente
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Função para virar o cartão
        function virarCartao() {
            const flashcard = document.getElementById('flashcard-atual');
            flashcard.classList.toggle('flipped');
            
            // Mostrar botões de dificuldade apenas quando o cartão estiver virado (mostrando a resposta)
            const dificuldadeBotoes = document.getElementById('dificuldade-botoes');
            if (flashcard.classList.contains('flipped')) {
                dificuldadeBotoes.classList.add('visible');
            } else {
                dificuldadeBotoes.classList.remove('visible');
            }
        }
        
        // Função para processar a resposta do usuário
        function responderCartao(qualidade) {
            // Se for o último cartão, mostrar modal de conclusão após o processamento
            if (cartaoAtual === totalCartoes - 1) {
                document.getElementById('qualidade').value = qualidade;
                document.getElementById('form-resposta').submit();
                return;
            }
            
            // Atualizar formulário e enviar
            document.getElementById('qualidade').value = qualidade;
            
            // Avançar para o próximo cartão
            cartaoAtual++;
            const proximoCartao = cartoes[cartaoAtual];
            
            // Atualizar o ID do cartão no formulário
            document.getElementById('cartao_id').value = proximoCartao.id;
            
            // Atualizar o progresso
            const progressoTexto = document.getElementById('progresso-texto');
            progressoTexto.textContent = `Cartão ${cartaoAtual + 1} de ${totalCartoes}`;
            
            const progressoBarra = document.getElementById('progresso-barra');
            const porcentagem = ((cartaoAtual + 1) / totalCartoes) * 100;
            progressoBarra.style.width = porcentagem + '%';
            progressoBarra.setAttribute('aria-valuenow', cartaoAtual + 1);
            
            // Atualizar conteúdo do cartão
            const cardFrente = document.getElementById('card-frente');
            const cardVerso = document.getElementById('card-verso');
            
            // Resetar a virada do cartão
            const flashcard = document.getElementById('flashcard-atual');
            flashcard.classList.remove('flipped');
            
            // Esconder botões de dificuldade
            const dificuldadeBotoes = document.getElementById('dificuldade-botoes');
            dificuldadeBotoes.classList.remove('visible');
            
            // Atualizar conteúdo
            cardFrente.innerHTML = proximoCartao.frente.replace(/\n/g, '<br>');
            cardVerso.innerHTML = proximoCartao.verso.replace(/\n/g, '<br>');
            
            // Enviar resposta para processamento no servidor
            document.getElementById('form-resposta').submit();
        }
        
        // Verificar se todos os cartões foram estudados
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($tem_cartoes && isset($_POST['acao']) && $_POST['acao'] === 'responder' && end($cartoes)['id'] == $_POST['cartao_id']): ?>
                const modalConcluido = new bootstrap.Modal(document.getElementById('modalConcluido'));
                modalConcluido.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>