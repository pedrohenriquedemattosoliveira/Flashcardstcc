<?php
// Iniciar sessão
session_start();

// Alternar o tema escuro
$_SESSION['tema_escuro'] = isset($_POST['tema_escuro']) ? true : false;

// Obter a página de referência (de onde veio a requisição)
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';

// Redirecionar de volta para a página anterior
header("Location: " . $referer);
exit;
?>