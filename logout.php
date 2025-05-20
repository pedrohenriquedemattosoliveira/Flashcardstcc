<?php
/**
 * logout.php - Encerra a sessão do usuário atual
 * 
 * Este arquivo utiliza a classe Usuario para realizar o logout
 * e redireciona o usuário para a página de login
 */

// Incluir arquivo de configuração
require_once 'config.php';

// Inicializar o sistema
$sistema = inicializarSistema();
$usuario = $sistema['usuario'];

// Realizar o logout
$resultado = $usuario->logout();

// Verificar se o logout foi bem-sucedido
if ($resultado['sucesso']) {
    // Redirecionar para a página de login com mensagem de sucesso
    header('Location: login.php?mensagem=' . urlencode($resultado['mensagem']) . '&tipo=success');
} else {
    // Redirecionar com mensagem de erro, caso ocorra algum problema
    header('Location: login.php?mensagem=' . urlencode('Erro ao fazer logout. Tente novamente.') . '&tipo=danger');
}
exit;
?>