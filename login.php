<?php
// Mostra erros para facilitar testes durante o desenvolvimento.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Usa a conexao com o banco.
include("conexao.php");

// Recebe email e senha do formulario de login.
// O fallback "usuario" existe caso algum input antigo tenha esse nome.
$email = trim($_POST['email'] ?? ($_POST['usuario'] ?? ''));
$senha = $_POST['senha'] ?? '';

// Se email ou senha estiverem vazios, nao tenta consultar o banco.
if ($email === '' || $senha === '') {
    echo "<script>
            alert('Preencha email e senha!');
            window.location.href='index2.html';
          </script>";
    exit;
}

// Busca no banco o usuario que tem o email digitado.
// LIMIT 1 garante que so uma linha sera retornada.
$sql = "SELECT id, nome, email, senha FROM usuarios WHERE email = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $usuarioId, $usuarioNome, $usuarioEmail, $usuarioSenha);
$usuarioEncontrado = mysqli_stmt_fetch($stmt);

$senhaCorreta = false;

if ($usuarioEncontrado) {
    // password_verify confere senha criptografada.
    // hash_equals foi mantido como apoio para senhas antigas que talvez tenham sido salvas sem hash.
    $senhaCorreta = password_verify($senha, $usuarioSenha) || hash_equals($usuarioSenha, $senha);
}

if ($senhaCorreta) {
    // Prepara valores para serem usados dentro do JavaScript com seguranca.
    $nomeUsuario = json_encode($usuarioNome, JSON_UNESCAPED_UNICODE);
    $emailUsuario = json_encode($usuarioEmail, JSON_UNESCAPED_UNICODE);
    $idUsuario = json_encode((string) $usuarioId, JSON_UNESCAPED_UNICODE);

    // Salva dados basicos do usuario no navegador.
    // O restante do site usa esses dados para saber quem esta logado e para criar pedidos.
    echo "<script>
            localStorage.setItem('logado', 'true');
            localStorage.setItem('nomeUsuario', $nomeUsuario);
            localStorage.setItem('usuarioEmail', $emailUsuario);
            localStorage.setItem('usuarioId', $idUsuario);
            alert('Login realizado com sucesso!');
            window.location.href='index.html';
          </script>";
} else {
    // Se nao achou o usuario ou a senha nao bateu, volta para o login.
    echo "<script>
            alert('Email ou senha incorretos!');
            window.location.href='index2.html';
          </script>";
}
?>
