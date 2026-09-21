
<?php
session_start();

if (isset($_SESSION["admin_logado"]) && $_SESSION["admin_logado"] === true) {
    header("Location: admin.php");
    exit;
}

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $senha = $_POST["senha"] ?? "";

    /*
     * ALTERE AQUI SE QUISER.
     *
     * Usuário: admin
     * Senha: Madison@123
     */

    $usuarioCorreto = "admin";
    $senhaCorreta = "Madison@123";

    if ($usuario === $usuarioCorreto && $senha === $senhaCorreta) {

        $_SESSION["admin_logado"] = true;
        $_SESSION["admin_usuario"] = $usuario;

        header("Location: admin.php");
        exit;

    } else {

        $erro = "Usuário ou senha incorretos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Madison Versalit | Acesso Administrativo</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;

    font-family: Arial, Helvetica, sans-serif;

    background:  #7a4006e3;
    color: #2b211b;
}

.login-box {

    width: 100%;
    max-width: 420px;

    background: #fff;

    padding: 40px;

    border-radius: 18px;

    border: 1px solid #eadfd5;

    box-shadow: 0 15px 40px rgba(50, 30, 10, .08);
}

.logo {
    text-align: center;
    margin-bottom: 30px;
}

.logo h1 {
    font-size: 27px;
    margin-bottom: 5px;
}

.logo span {
    color: #a16d34;
    font-size: 11px;
    letter-spacing: 3px;
}

.logo p {
    margin-top: 15px;
    color: #806f63;
    font-size: 14px;
}

.field {
    display: grid;
    gap: 7px;
    margin-bottom: 18px;
}

.field label {
    font-size: 13px;
    font-weight: 600;
}

.field input {
    width: 100%;
    padding: 13px;

    border: 1px solid #dfd2c7;
    border-radius: 9px;

    outline: none;
}

.field input:focus {
    border-color: #a16d34;
    box-shadow: 0 0 0 3px rgba(161,109,52,.1);
}

.btn {
    width: 100%;

    border: 0;
    border-radius: 9px;

    padding: 13px;

    background: #8a541d;
    color: #fff;

    font-weight: 600;

    cursor: pointer;
}

.btn:hover {
    background: #704315;
}

.error {
    background: #fff0f0;
    color: #a33;

    border: 1px solid #f1cccc;

    padding: 12px;
    border-radius: 9px;

    margin-bottom: 18px;

    font-size: 13px;
}

.voltar {
    display: block;

    text-align: center;

    margin-top: 20px;

    color: #806f63;

    font-size: 13px;
}

.voltar:hover {
    color: #8a541d;
}

</style>

</head>

<body>

<div class="login-box">

    <div class="logo">

        <h1>Madison Versalit</h1>

        <span>PAINEL ADMINISTRATIVO</span>

        <p>
            Acesse o painel para gerenciar sua loja.
        </p>

    </div>

    <?php if ($erro): ?>

        <div class="error">
            <?= htmlspecialchars($erro, ENT_QUOTES, "UTF-8") ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="field">

            <label>
                Usuário
            </label>

            <input
                type="text"
                name="usuario"
                required
                autocomplete="username"
                placeholder="Digite seu usuário"
            >

        </div>

        <div class="field">

            <label>
                Senha
            </label>

            <input
                type="password"
                name="senha"
                required
                autocomplete="current-password"
                placeholder="Digite sua senha"
            >

        </div>

        <button
            class="btn"
            type="submit"
        >
            Entrar no painel
        </button>

    </form>

    <a
        class="voltar"
        href="index.html"
    >
        ← Voltar para a loja
    </a>

</div>

</body>

</html>