<?php
require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();
$sistema = inicializarSistema();

// Obter dados do usuário
$usuario = $sistema['usuario']->obterPorId($usuario_id);

// Obter estatísticas gerais
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

// Cartões estudados hoje
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM cartoes c
    JOIN baralhos b ON c.baralho_id = b.id
    JOIN estatisticas e ON c.id = e.cartao_id
    WHERE b.usuario_id = ? AND e.ultima_revisao = ?
");
$stmt->execute([$usuario_id, $hoje]);
$cartoes_estudados_hoje = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Cartões estudados na última semana
$ultima_semana = date('Y-m-d', strtotime('-7 days'));
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT c.id) as total 
    FROM cartoes c
    JOIN baralhos b ON c.baralho_id = b.id
    JOIN estatisticas e ON c.id = e.cartao_id
    WHERE b.usuario_id = ? AND e.ultima_revisao >= ?
");
$stmt->execute([$usuario_id, $ultima_semana]);
$cartoes_estudados_semana = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Baralhos com mais cartões para revisar
$stmt = $conn->prepare("
    SELECT b.*, 
           COUNT(c.id) as total_cartoes,
           COUNT(CASE WHEN e.proxima_revisao <= ? THEN 1 END) as cartoes_para_revisar
    FROM baralhos b
    LEFT JOIN cartoes c ON b.id = c.baralho_id
    LEFT JOIN estatisticas e ON c.id = e.cartao_id
    WHERE b.usuario_id = ?
    GROUP BY b.id
    ORDER BY cartoes_para_revisar DESC, b.nome ASC
    LIMIT 5
");
$stmt->execute([$hoje, $usuario_id]);
$baralhos_prioritarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Baralhos criados recentemente
$stmt = $conn->prepare("
    SELECT b.*, 
           COUNT(c.id) as total_cartoes
    FROM baralhos b
    LEFT JOIN cartoes c ON b.id = c.baralho_id
    WHERE b.usuario_id = ?
    GROUP BY b.id
    ORDER BY b.data_criacao DESC
    LIMIT 5
");
$stmt->execute([$usuario_id]);
$baralhos_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Atividade dos últimos 7 dias (para gráfico simples)
$atividade_semana = [];
for ($i = 6; $i >= 0; $i--) {
    $data = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM cartoes c
        JOIN baralhos b ON c.baralho_id = b.id
        JOIN estatisticas e ON c.id = e.cartao_id
        WHERE b.usuario_id = ? AND e.ultima_revisao = ?
    ");
    $stmt->execute([$usuario_id, $data]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $atividade_semana[] = [
        'data' => $data,
        'dia' => date('D', strtotime($data)),
        'total' => $total
    ];
}

// Incluir o cabeçalho
$titulo_pagina = "Dashboard";
include 'includes/header.php';
?>

<div class="container mt-4">
    <!-- Boas-vindas -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h1 class="card-title h2 mb-2">Olá, <?php echo htmlspecialchars($usuario['nome']); ?>! 👋</h1>
                    <p class="card-text mb-3">Bem-vindo ao seu dashboard de estudos. Aqui você pode acompanhar seu progresso e continuar seus estudos.</p>
                    
                    <?php if ($cartoes_para_revisar > 0): ?>
                        <div class="alert alert-warning mb-0" role="alert">
                            <strong>📚 Você tem <?php echo $cartoes_para_revisar; ?> cartão(ões) para revisar hoje!</strong>
                            <a href="estudar.php" class="btn btn-warning btn-sm ms-2">Estudar Agora</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-0" role="alert">
                            <strong>🎉 Parabéns! Você está em dia com suas revisões!</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas principais -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-primary">
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-2">📚</div>
                    <h5 class="card-title">Baralhos</h5>
                    <h2 class="text-primary"><?php echo $total_baralhos; ?></h2>
                    <a href="baralhos.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-info">
                <div class="card-body text-center">
                    <div class="display-4 text-info mb-2">🃏</div>
                    <h5 class="card-title">Total de Cartões</h5>
                    <h2 class="text-info"><?php echo $total_cartoes; ?></h2>
                    <small class="text-muted">Em todos os baralhos</small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-body text-center">
                    <div class="display-4 text-warning mb-2">⏰</div>
                    <h5 class="card-title">Para Revisar</h5>
                    <h2 class="text-warning"><?php echo $cartoes_para_revisar; ?></h2>
                    <?php if ($cartoes_para_revisar > 0): ?>
                        <a href="estudar.php" class="btn btn-sm btn-warning">Estudar</a>
                    <?php else: ?>
                        <small class="text-muted">Tudo em dia!</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card h-100 border-success">
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-2">✅</div>
                    <h5 class="card-title">Estudados Hoje</h5>
                    <h2 class="text-success"><?php echo $cartoes_estudados_hoje; ?></h2>
                    <small class="text-muted">Continue assim!</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações rápidas -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Ações Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="criar_baralho.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Novo Baralho
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="estudar.php" class="btn btn-success w-100">
                                <i class="fas fa-brain"></i> Estudar
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="baralhos.php" class="btn btn-info w-100">
                                <i class="fas fa-layer-group"></i> Meus Baralhos
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="perfil.php" class="btn btn-secondary w-100">
                                <i class="fas fa-user"></i> Meu Perfil
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Baralhos prioritários -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0">🔥 Baralhos Prioritários</h5>
                    <small>Baralhos com mais cartões para revisar</small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($baralhos_prioritarios)): ?>
                        <div class="p-3 text-center text-muted">
                            <p>Nenhum baralho encontrado.</p>
                            <a href="criar_baralho.php" class="btn btn-primary">Criar Primeiro Baralho</a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($baralhos_prioritarios as $baralho): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <a href="baralho.php?id=<?php echo $baralho['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($baralho['nome']); ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo $baralho['total_cartoes']; ?> cartões total
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($baralho['cartoes_para_revisar'] > 0): ?>
                                            <span class="badge bg-warning text-dark rounded-pill mb-1">
                                                <?php echo $baralho['cartoes_para_revisar']; ?> para revisar
                                            </span>
                                            <br>
                                            <a href="estudar.php?baralho=<?php echo $baralho['id']; ?>" class="btn btn-sm btn-warning">
                                                Estudar
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill">
                                                Em dia!
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="baralhos.php" class="btn btn-sm btn-outline-warning">Ver Todos os Baralhos</a>
                </div>
            </div>
        </div>

        <!-- Atividade recente -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">📊 Atividade da Semana</h5>
                    <small>Cartões estudados nos últimos 7 dias</small>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <?php foreach ($atividade_semana as $dia): ?>
                            <div class="col">
                                <div class="mb-2">
                                    <div class="badge bg-<?php echo $dia['total'] > 0 ? 'success' : 'light text-dark'; ?> rounded-circle p-3">
                                        <?php echo $dia['total']; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo $dia['dia']; ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-success"><?php echo $cartoes_estudados_semana; ?></h4>
                                <small class="text-muted">Esta semana</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo round($cartoes_estudados_semana / 7, 1); ?></h4>
                            <small class="text-muted">Média por dia</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="estudar.php" class="btn btn-sm btn-success">Continuar Estudando</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Baralhos recentes -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">📝 Baralhos Criados Recentemente</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($baralhos_recentes)): ?>
                        <div class="p-3 text-center text-muted">
                            <p>Nenhum baralho criado ainda.</p>
                            <a href="criar_baralho.php" class="btn btn-primary">Criar Primeiro Baralho</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th>Descrição</th>
                                        <th>Cartões</th>
                                        <th>Criado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($baralhos_recentes as $baralho): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($baralho['nome']); ?></strong>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php 
                                                    $desc = htmlspecialchars($baralho['descricao']);
                                                    echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc;
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary rounded-pill">
                                                    <?php echo $baralho['total_cartoes']; ?> cartões
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($baralho['data_criacao'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="baralho.php?id=<?php echo $baralho['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                        Ver
                                                    </a>
                                                    <?php if ($baralho['total_cartoes'] > 0): ?>
                                                        <a href="estudar.php?baralho=<?php echo $baralho['id']; ?>" class="btn btn-outline-success btn-sm">
                                                            Estudar
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="baralhos.php" class="btn btn-sm btn-info">Ver Todos os Baralhos</a>
                    <a href="criar_baralho.php" class="btn btn-sm btn-primary">Criar Novo Baralho</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>