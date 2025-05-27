<?php
require_once 'config.php';

$usuario_id = verificarLogin();

$sistema = inicializarSistema();
$usuario = $sistema['usuario'];






$mensagem = '';
$tipo_alerta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['acao']) && $_POST['acao'] === 'atualizar_perfil') {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        
        if (empty($nome) || empty($email)) {
            $mensagem = 'Nome e e-mail são obrigatórios.';
            $tipo_alerta = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem = 'E-mail inválido.';
            $tipo_alerta = 'danger';
        } elseif (!empty($nova_senha) && $nova_senha !== $confirmar_senha) {
            $mensagem = 'As senhas não conferem.';
            $tipo_alerta = 'danger';
        } else {
            $stmt = $sistema['usuario']->db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $usuario_id]);
            if ($stmt->fetch()) {
                $mensagem = 'Este e-mail já está sendo usado por outro usuário.';
                $tipo_alerta = 'danger';
            } else {
                if (!empty($nova_senha)) {
                    // Buscar a senha atual do usuário
                    $stmt = $sistema['usuario']->db->prepare("SELECT senha FROM usuarios WHERE id = ?");
                    $stmt->execute([$usuario_id]);
                    $usuario_senha = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!password_verify($senha_atual, $usuario_senha['senha'])) {
                        $mensagem = 'Senha atual incorreta.';
                        $tipo_alerta = 'danger';
                    } else {
                        // Atualizar com a nova senha
                        $resultado = $usuario->atualizarPerfil($usuario_id, $nome, $email, $nova_senha);
                        $mensagem = $resultado['mensagem'];
                        $tipo_alerta = $resultado['sucesso'] ? 'success' : 'danger';
                    }
                } else {
                    // Atualizar sem alterar a senha
                    $resultado = $usuario->atualizarPerfil($usuario_id, $nome, $email);
                    $mensagem = $resultado['mensagem'];
                    $tipo_alerta = $resultado['sucesso'] ? 'success' : 'danger';
                }
            }
        }
    } elseif (isset($_POST['acao']) && $_POST['acao'] === 'tema_escuro') {
        // Salvar preferência de tema na sessão
        $_SESSION['tema_escuro'] = isset($_POST['tema_escuro']) ? true : false;
        $mensagem = 'Preferências de tema atualizadas com sucesso.';
        $tipo_alerta = 'success';
    } elseif (isset($_POST['acao']) && $_POST['acao'] === 'notificacoes') {
        // Salvar preferências de notificações (poderia ser expandido para salvar no banco de dados)
        $_SESSION['notificacoes_email'] = isset($_POST['notificacoes_email']) ? true : false;
        $_SESSION['notificacoes_diarias'] = isset($_POST['notificacoes_diarias']) ? true : false;
        $mensagem = 'Preferências de notificações atualizadas com sucesso.';
        $tipo_alerta = 'success';
    } elseif (isset($_POST['acao']) && $_POST['acao'] === 'exportar_dados') {
        // Lógica para exportar os dados do usuário (baralhos e cartões)
        // Esta é uma implementação básica para download de dados em JSON
        
        // Buscar baralhos do usuário
        $baralhos = $sistema['baralho']->listar($usuario_id);
        $dados_exportacao = ['baralhos' => []];
        
        foreach ($baralhos as $baralho) {
            $cartoes = $sistema['cartao']->listar($baralho['id']);
            $dados_baralho = [
                'id' => $baralho['id'],
                'nome' => $baralho['nome'],
                'descricao' => $baralho['descricao'],
                'data_criacao' => $baralho['data_criacao'],
                'cartoes' => []
            ];
            
            foreach ($cartoes as $cartao) {
                $tags = $sistema['tag']->listarPorCartao($cartao['id']);
                $dados_cartao = [
                    'id' => $cartao['id'],
                    'frente' => $cartao['frente'],
                    'verso' => $cartao['verso'],
                    'data_criacao' => $cartao['data_criacao'],
                    'estatisticas' => [
                        'facilidade' => $cartao['facilidade'],
                        'intervalo' => $cartao['intervalo'],
                        'repeticoes' => $cartao['repeticoes'],
                        'proxima_revisao' => $cartao['proxima_revisao'],
                        'ultima_revisao' => $cartao['ultima_revisao']
                    ],
                    'tags' => array_map(function($tag) {
                        return $tag['nome'];
                    }, $tags)
                ];
                
                $dados_baralho['cartoes'][] = $dados_cartao;
            }
            
            $dados_exportacao['baralhos'][] = $dados_baralho;
        }
        
        // Informações do usuário (exceto senha)
        $info_usuario = $usuario->obterPorId($usuario_id);
        $dados_exportacao['usuario'] = [
            'id' => $info_usuario['id'],
            'nome' => $info_usuario['nome'],
            'email' => $info_usuario['email'],
            'data_cadastro' => $info_usuario['data_cadastro']
        ];
        
        // Gerar arquivo JSON para download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="meus_flashcards_export_' . date('Y-m-d') . '.json"');
        echo json_encode($dados_exportacao, JSON_PRETTY_PRINT);
        exit;
    }
}








// Processar formulário de atualização de perfil
$mensagem = '';
$tipo_alerta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... outros casos de processamento de formulário ...

    if (isset($_POST['acao']) && $_POST['acao'] === 'tema_escuro') {
        // Salvar preferência de tema na sessão
        $_SESSION['tema_escuro'] = isset($_POST['tema_escuro']) ? true : false;
        $mensagem = 'Preferências de tema atualizadas com sucesso.';
        $tipo_alerta = 'success';
        
        // Redirecionar para a mesma página para aplicar o tema imediatamente
        header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['tab']) ? "?tab=" . $_GET['tab'] : ""));
        exit;
    }
    
    // ... outros casos de processamento de formulário ...
}

// Obter informações do usuário
$info_usuario = $usuario->obterPorId($usuario_id);

// Verificar tema escuro e outras preferências
verificarTemaEscuro();
if (!isset($_SESSION['notificacoes_email'])) {
    $_SESSION['notificacoes_email'] = true;
}
if (!isset($_SESSION['notificacoes_diarias'])) {
    $_SESSION['notificacoes_diarias'] = true;
}




// Obter informações do usuário
$info_usuario = $usuario->obterPorId($usuario_id);

// Verificar se já existem preferências salvas na sessão
if (!isset($_SESSION['tema_escuro'])) {
    $_SESSION['tema_escuro'] = false;
}
if (!isset($_SESSION['notificacoes_email'])) {
    $_SESSION['notificacoes_email'] = true;
}
if (!isset($_SESSION['notificacoes_diarias'])) {
    $_SESSION['notificacoes_diarias'] = true;
}

// Título da página
$titulo = "Configurações";
?>

<!DOCTYPE html>
<html lang="pt-br" <?php if ($_SESSION['tema_escuro']): ?>data-bs-theme="dark"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Flashcards - <?php echo $titulo; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .nav-pills .nav-link.active {
            background-color: rgba(var(--bs-primary-rgb), 0.8);
        }
        .form-check-input:checked {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1 class="mb-4">
            <i class="bi bi-gear-fill me-2"></i>
            <?php echo $titulo; ?>
        </h1>
        
        <?php if (!empty($mensagem)): ?>
            <?php echo exibirAlerta($tipo_alerta, $mensagem); ?>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="list-group">
                    <a class="list-group-item list-group-item-action active" data-bs-toggle="list" href="#perfil">
                        <i class="bi bi-person-fill me-2"></i>Perfil
                    </a>
                
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#notificacoes">
                        <i class="bi bi-bell-fill me-2"></i>Notificações
                    </a>
                    <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#exportacao">
                        <i class="bi bi-download me-2"></i>Exportação de Dados
                    </a>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- Perfil -->
                    <div class="tab-pane fade show active" id="perfil">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Informações do Perfil</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="acao" value="atualizar_perfil">
                                    
                                    <div class="mb-3">
                                        <label for="nome" class="form-label">Nome</label>
                                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($info_usuario['nome']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">E-mail</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($info_usuario['email']); ?>" required>
                                    </div>
                                    
                                    <hr>
                                    <h5>Alterar Senha</h5>
                                    <p class="text-muted">Preencha apenas se desejar alterar sua senha</p>
                                    
                                    <div class="mb-3">
                                        <label for="senha_atual" class="form-label">Senha Atual</label>
                                        <input type="password" class="form-control" id="senha_atual" name="senha_atual">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="nova_senha" class="form-label">Nova Senha</label>
                                        <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aparência -->
                    <div class="tab-pane fade" id="aparencia">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Personalização da Interface</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="acao" value="tema_escuro">
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="tema_escuro" name="tema_escuro" <?php if ($_SESSION['tema_escuro']): ?>checked<?php endif; ?>>
                                        <label class="form-check-label" for="tema_escuro">Modo Escuro</label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Salvar Preferências</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notificações -->
                    <div class="tab-pane fade" id="notificacoes">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Preferências de Notificação</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="acao" value="notificacoes">
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="notificacoes_email" name="notificacoes_email" <?php if ($_SESSION['notificacoes_email']): ?>checked<?php endif; ?>>
                                        <label class="form-check-label" for="notificacoes_email">
                                            Receber notificações por e-mail
                                        </label>
                                        <div class="text-muted small">Enviaremos lembretes sobre cartões para revisar</div>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="notificacoes_diarias" name="notificacoes_diarias" <?php if ($_SESSION['notificacoes_diarias']): ?>checked<?php endif; ?>>
                                        <label class="form-check-label" for="notificacoes_diarias">
                                            Resumo diário de atividades
                                        </label>
                                        <div class="text-muted small">Receba um resumo diário dos seus estudos</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Salvar Preferências</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exportação de Dados -->
                    <div class="tab-pane fade" id="exportacao">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Exportar Seus Dados</h5>
                            </div>
                            <div class="card-body">
                                <p>Você pode exportar todos os seus dados, incluindo baralhos, cartões e estatísticas de aprendizado. Isso é útil para fazer backup ou transferir seus dados para outro sistema.</p>
                                
                                <form method="post">
                                    <input type="hidden" name="acao" value="exportar_dados">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-download me-2"></i>Exportar em JSON
                                    </button>
                                </form>
                                
                                <hr>
                                
                                <h5>Importar Dados</h5>
                                <p>Você pode importar dados previamente exportados no formato JSON.</p>
                                
                                <form method="post" action="importar_dados.php" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="arquivo_import" class="form-label">Selecionar arquivo JSON</label>
                                        <input type="file" class="form-control" id="arquivo_import" name="arquivo_import" accept=".json">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-upload me-2"></i>Importar Dados
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para alternar entre as abas
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar se há parâmetro de aba na URL
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab');
            
            if (activeTab) {
                // Ativar a aba especificada na URL
                const tab = document.querySelector(`a[href="#${activeTab}"]`);
                if (tab) {
                    const bsTab = new bootstrap.Tab(tab);
                    bsTab.show();
                }
            }
            
            // Adicionar evento para atualizar URL quando a aba mudar
            const tabEls = document.querySelectorAll('a[data-bs-toggle="list"]');
            tabEls.forEach(tabEl => {
                tabEl.addEventListener('shown.bs.tab', function (event) {
                    const id = event.target.getAttribute('href').substring(1);
                    history.replaceState(null, null, `?tab=${id}`);
                });
            });
        });
    </script>
</body>
</html>