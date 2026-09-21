<?php
// Mostra erros durante o desenvolvimento. Ajuda a encontrar problemas de cadastro.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Importa a conexao com o banco criada em conexao.php.
include("conexao.php");

// Recebe os dados enviados pelo formulario de cadastro em index2.html.
// trim() remove espacos extras no comeco e no fim.
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// Impede cadastro vazio. Se faltar algum campo, volta para a tela de login/cadastro.
if ($nome === '' || $email === '' || $senha === '') {
    echo "<script>
            alert('Preencha todos os campos!');
            window.location.href='index2.html';
          </script>";
    exit;
}

// Criptografa a senha antes de salvar no banco.
// Assim a senha real do usuario nao fica gravada diretamente na tabela.
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

try {
    // Prepared statement: coloca os valores no INSERT com seguranca.
    // Isso evita SQL Injection e cadastra o usuario na tabela usuarios.
    $sql = "INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $nome, $email, $senhaHash);
    mysqli_stmt_execute($stmt);

    // Se deu certo, avisa o usuario e volta para a tela de login.
    echo "<script>
            alert('Cadastro realizado com sucesso!');
            window.location.href='index2.html';
          </script>";
} catch (mysqli_sql_exception $erro) {
    // Codigo 1062 normalmente significa valor duplicado, como email ja cadastrado.
    if ($erro->getCode() === 1062) {
        echo "<script>
                alert('Este email ja esta cadastrado!');
                window.location.href='index2.html';
              </script>";
    } else {
        echo "Erro ao cadastrar: " . $erro->getMessage();
    }
}
?>
