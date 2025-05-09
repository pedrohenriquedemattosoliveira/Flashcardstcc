<?php
require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não autenticado']);
    exit;
}

// Verificar se os parâmetros necessários foram enviados
if (!isset($_POST['cartao_id']) || !isset($_POST['qualidade'])) {
    header('Content-Type: application/json');
    echo json_encode(['sucesso' => false, 'mensagem' => 'Parâmetros inválidos']);
    exit;
}

// Inicializar o sistema
$db = new Database($config);
$conn = $db->getConnection();
$repetidor = new RepetidorEspacado($conn);

// Processar a resposta
$cartao_id = (int)$_POST['cartao_id'];
$qualidade = (int)$_POST['qualidade'];

$resultado = $repetidor->processarResposta($cartao_id, $qualidade);

// Retornar o resultado como JSON
header('Content-Type: application/json');
echo json_encode($resultado);
?>