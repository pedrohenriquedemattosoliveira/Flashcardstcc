<?php


// Database configuration
define('DB_HOST', 'localhost');  // Your database host
define('DB_USER', 'root');   // Your database username
define('DB_PASS', '');   // Your database password
// Any other configuration constants your application needs

// Function to verify login

require_once 'config.php';

// Verificar se o usuário está logado
$usuario_id = verificarLogin();

// Conexão com o banco de dados usando PDO
try {
    $dsn = "mysql:host=".DB_HOST.";dbname=sistema_flashcards;charset=utf8";
    $conn = new PDO($dsn, DB_USER, DB_PASS);
    // Configurar PDO para lançar exceções em caso de erros
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Definir o modo de busca padrão como associativo
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Falha na conexão: " . $e->getMessage());
}

// Obter estatísticas gerais do usuário
$query_baralhos = "SELECT COUNT(*) as total_baralhos FROM baralhos WHERE usuario_id = :usuario_id";
$stmt_baralhos = $conn->prepare($query_baralhos);
$stmt_baralhos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_baralhos->execute();
$total_baralhos = $stmt_baralhos->fetch()['total_baralhos'];

// Contar total de cartões
$query_cartoes = "SELECT COUNT(*) as total_cartoes 
                 FROM cartoes c 
                 JOIN baralhos b ON c.baralho_id = b.id 
                 WHERE b.usuario_id = :usuario_id";
$stmt_cartoes = $conn->prepare($query_cartoes);
$stmt_cartoes->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_cartoes->execute();
$total_cartoes = $stmt_cartoes->fetch()['total_cartoes'];

// Contar cartões para revisar (onde a data de próxima revisão é hoje ou anterior)
$hoje = date('Y-m-d');
$query_revisar = "SELECT COUNT(*) as cartoes_revisar 
                 FROM estatisticas e 
                 JOIN cartoes c ON e.cartao_id = c.id 
                 JOIN baralhos b ON c.baralho_id = b.id 
                 WHERE b.usuario_id = :usuario_id AND (e.proxima_revisao <= :hoje OR e.proxima_revisao IS NULL)";
$stmt_revisar = $conn->prepare($query_revisar);
$stmt_revisar->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_revisar->bindParam(':hoje', $hoje, PDO::PARAM_STR);
$stmt_revisar->execute();
$cartoes_revisar = $stmt_revisar->fetch()['cartoes_revisar'];

// Obter histórico de estudos (últimos 30 dias)
$query_historico = "SELECT 
                    DATE(e.ultima_revisao) as data,
                    COUNT(*) as total_revisoes,
                    SUM(CASE WHEN e.facilidade > 2.5 THEN 1 ELSE 0 END) as acertos
                  FROM estatisticas e 
                  JOIN cartoes c ON e.cartao_id = c.id 
                  JOIN baralhos b ON c.baralho_id = b.id 
                  WHERE b.usuario_id = :usuario_id 
                    AND e.ultima_revisao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    AND e.ultima_revisao IS NOT NULL
                  GROUP BY DATE(e.ultima_revisao)
                  ORDER BY data ASC";
$stmt_historico = $conn->prepare($query_historico);
$stmt_historico->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_historico->execute();
$result_historico = $stmt_historico->fetchAll();

$labels_dias = [];
$dados_revisoes = [];
$dados_acertos = [];

$dias_estudados = 0;
$total_revisoes = 0;
$total_acertos = 0;

foreach ($result_historico as $row) {
    $labels_dias[] = date('d/m', strtotime($row['data']));
    $dados_revisoes[] = $row['total_revisoes'];
    $dados_acertos[] = $row['acertos'];
    
    $dias_estudados++;
    $total_revisoes += $row['total_revisoes'];
    $total_acertos += $row['acertos'];
}

// Calcular taxa de acertos
$taxa_acertos = 0;
if ($total_revisoes > 0) {
    $taxa_acertos = round(($total_acertos / $total_revisoes) * 100, 2);
}

// Obter status dos cartões
$query_status = "SELECT 
                 SUM(CASE WHEN e.repeticoes = 0 THEN 1 ELSE 0 END) as cartoes_novos,
                 SUM(CASE WHEN e.repeticoes BETWEEN 1 AND 5 THEN 1 ELSE 0 END) as cartoes_aprendendo,
                 SUM(CASE WHEN e.repeticoes > 5 THEN 1 ELSE 0 END) as cartoes_dominados
               FROM estatisticas e
               JOIN cartoes c ON e.cartao_id = c.id
               JOIN baralhos b ON c.baralho_id = b.id
               WHERE b.usuario_id = :usuario_id";
$stmt_status = $conn->prepare($query_status);
$stmt_status->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_status->execute();
$status_cartoes = $stmt_status->fetch();

// Definir valores padrão caso sejam NULL
$cartoes_novos = $status_cartoes['cartoes_novos'] ?? 0;
$cartoes_aprendendo = $status_cartoes['cartoes_aprendendo'] ?? 0;
$cartoes_dominados = $status_cartoes['cartoes_dominados'] ?? 0;

// Obter desempenho por baralho
$query_baralhos_desempenho = "SELECT 
                             b.id,
                             b.nome,
                             COUNT(DISTINCT c.id) as total_cartoes,
                             COUNT(DISTINCT CASE WHEN e.ultima_revisao IS NOT NULL THEN e.id END) as total_revisoes,
                             SUM(CASE WHEN e.facilidade > 2.5 THEN 1 ELSE 0 END) as acertos,
                             SUM(CASE WHEN e.repeticoes > 5 THEN 1 ELSE 0 END) as cartoes_dominados
                           FROM baralhos b
                           LEFT JOIN cartoes c ON b.id = c.baralho_id
                           LEFT JOIN estatisticas e ON c.id = e.cartao_id
                           WHERE b.usuario_id = :usuario_id
                           GROUP BY b.id, b.nome
                           ORDER BY b.nome";
$stmt_baralhos_desempenho = $conn->prepare($query_baralhos_desempenho);
$stmt_baralhos_desempenho->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_baralhos_desempenho->execute();
$desempenho_baralhos = $stmt_baralhos_desempenho->fetchAll();

// Dados adicionais para a página (estes seriam normalmente calculados com mais precisão)
$sequencia_atual = 0;
$maior_sequencia = 0;
$recorde_revisoes_dia = 0;
$melhor_taxa_acertos = 0;
$tempo_total_minutos = 0;
$recorde_tempo_dia = 0;

// Encontrar o maior número de revisões em um dia
foreach ($dados_revisoes as $revisoes) {
    if ($revisoes > $recorde_revisoes_dia) {
        $recorde_revisoes_dia = $revisoes;
    }
}

// Calcular tempo estimado (assumindo 30 segundos por revisão)
$tempo_total_minutos = round(($total_revisoes * 30) / 60);
$recorde_tempo_dia = round(($recorde_revisoes_dia * 30) / 60);

// Não é necessário fechar conexão com PDO - ela será fechada automaticamente quando o script terminar
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatísticas - Sistema de Flashcards</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/estatisticas.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
 
</head>
<body>
    <!-- Barra de navegação -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12 mb-4">
                <h2>Estatísticas de Estudo</h2>
                <p class="text-muted">Acompanhe seu progresso e performance nos estudos com flashcards</p>
            </div>
        </div>

        <!-- Estatísticas Gerais -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-primary stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-check fa-2x mb-2"></i>
                        <h5 class="card-title">Dias de Estudo</h5>
                        <p class="card-text display-4"><?php echo $dias_estudados; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-success stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-sync-alt fa-2x mb-2"></i>
                        <h5 class="card-title">Total de Revisões</h5>
                        <p class="card-text display-4"><?php echo $total_revisoes; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-info stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h5 class="card-title">Taxa de Acertos</h5>
                        <p class="card-text display-4"><?php echo $taxa_acertos; ?>%</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-warning stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-hourglass-half fa-2x mb-2"></i>
                        <h5 class="card-title">Tempo Total</h5>
                        <p class="card-text display-4"><?php echo floor($tempo_total_minutos / 60); ?>h</p>
                        <p class="card-text"><?php echo $tempo_total_minutos % 60; ?> min</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4">
            <!-- Gráfico de revisões por dia -->
            <div class="col-md-8 mb-3">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Revisões nos Últimos 30 Dias</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <?php if (empty($labels_dias)): ?>
                                <div class="alert alert-info">
                                    Não há dados de revisão nos últimos 30 dias.
                                </div>
                            <?php else: ?>
                                <canvas id="revisoesDiariasChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de status dos cartões -->
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Status dos Cartões</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="chart-container mb-3" style="position: relative; height: 250px;">
                            <?php if ($total_cartoes == 0): ?>
                                <div class="alert alert-info">
                                    Não há cartões cadastrados ainda.
                                </div>
                            <?php else: ?>
                                <canvas id="statusCartoesChart"></canvas>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($total_cartoes > 0): ?>
                        <div class="mt-auto">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-primary me-2" style="width: 15px; height: 15px;"></span>
                                        Novos
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?php echo $cartoes_novos; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-warning me-2" style="width: 15px; height: 15px;"></span>
                                        Aprendendo
                                    </div>
                                    <span class="badge bg-warning rounded-pill"><?php echo $cartoes_aprendendo; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 border-bottom-0">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-success me-2" style="width: 15px; height: 15px;"></span>
                                        Dominados
                                    </div>
                                    <span class="badge bg-success rounded-pill"><?php echo $cartoes_dominados; ?></span>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <!-- Tabela de desempenho por baralho -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Desempenho por Baralho</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($desempenho_baralhos)): ?>
                            <div class="alert alert-info">
                                Você ainda não possui baralhos. <a href="novo_baralho.php">Crie seu primeiro baralho</a>.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Baralho</th>
                                            <th>Cartões</th>
                                            <th>Revisões</th>
                                            <th>Acertos</th>
                                            <th>Taxa de Acerto</th>
                                            <th>Progresso</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($desempenho_baralhos as $baralho): ?>
                                        <?php 
                                            $taxa_acerto_baralho = 0;
                                            if ($baralho['total_revisoes'] > 0) {
                                                $taxa_acerto_baralho = round(($baralho['acertos'] / $baralho['total_revisoes']) * 100, 1);
                                            }
                                            
                                            $progresso = 0;
                                            if ($baralho['total_cartoes'] > 0) {
                                                $progresso = round(($baralho['cartoes_dominados'] / $baralho['total_cartoes']) * 100, 1);
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($baralho['nome']); ?></td>
                                            <td><?php echo $baralho['total_cartoes']; ?></td>
                                            <td><?php echo $baralho['total_revisoes']; ?></td>
                                            <td><?php echo $baralho['acertos']; ?></td>
                                            <td><?php echo $taxa_acerto_baralho; ?>%</td>
                                            <td>
                                                <div class="progress position-relative" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $progresso; ?>%" 
                                                         aria-valuenow="<?php echo $progresso; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100"></div>
                                                    <span class="progress-label"><?php echo $progresso; ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="gerenciar_baralho.php?id=<?php echo $baralho['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-cog"></i>
                                                </a>
                                                <a href="estudar.php?baralho_id=<?php echo $baralho['id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-play"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sequência de estudo e recordes -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Sequência de Estudo</h5>
                    </div>
                    <div class="card-body text-center">
                        <h2 class="display-1"><?php echo $sequencia_atual; ?></h2>
                        <p class="text-muted">Dias consecutivos de estudo</p>
                        <p>Maior sequência: <strong><?php echo $maior_sequencia; ?> dias</strong></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Recordes Pessoais</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-trophy text-warning me-2"></i>
                                    Maior número de revisões em um dia
                                </div>
                                <span class="badge bg-primary rounded-pill"><?php echo $recorde_revisoes_dia; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-trophy text-warning me-2"></i>
                                    Melhor taxa de acertos em um dia
                                </div>
                                <span class="badge bg-success rounded-pill"><?php echo $taxa_acertos; ?>%</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-trophy text-warning me-2"></i>
                                    Maior tempo de estudo em um dia
                                </div>
                                <span class="badge bg-info rounded-pill"><?php echo floor($recorde_tempo_dia / 60); ?>h <?php echo $recorde_tempo_dia % 60; ?>min</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts para os gráficos -->
    <?php if (!empty($labels_dias) || $total_cartoes > 0): ?>
    <script>
        <?php if (!empty($labels_dias)): ?>
        // Gráfico de revisões diárias
        const revisoesDiariasCtx = document.getElementById('revisoesDiariasChart').getContext('2d');
        const revisoesDiariasChart = new Chart(revisoesDiariasCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_dias); ?>,
                datasets: [
                    {
                        label: 'Revisões',
                        data: <?php echo json_encode($dados_revisoes); ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Acertos',
                        data: <?php echo json_encode($dados_acertos); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($total_cartoes > 0): ?>
        // Gráfico de status dos cartões
        const statusCartoesCtx = document.getElementById('statusCartoesChart').getContext('2d');
        const statusCartoesChart = new Chart(statusCartoesCtx, {
            type: 'doughnut',
            data: {
                labels: ['Novos', 'Aprendendo', 'Dominados'],
                datasets: [{
                    data: [
                        <?php echo $cartoes_novos; ?>,
                        <?php echo $cartoes_aprendendo; ?>,
                        <?php echo $cartoes_dominados; ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>