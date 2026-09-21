<?php

session_start();

if (
    !isset($_SESSION["admin_logado"]) ||
    $_SESSION["admin_logado"] !== true
) {
    header("Location: admin_login.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . "/conexao.php";

$pagina = $_GET["pagina"] ?? "dashboard";
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . "/conexao.php";


$mensagem = "";
$erro = "";
$erroDashboard = "";


/* =========================================================
   FUNÇÕES
========================================================= */

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function moeda($valor) {
    return "R$ " . number_format((float)$valor, 2, ",", ".");
}


/* =========================================================
   UPLOAD DE IMAGEM
========================================================= */

function salvarImagem($arquivo) {

    if (!isset($arquivo) || $arquivo["error"] === UPLOAD_ERR_NO_FILE) {
        return "";
    }

    if ($arquivo["error"] !== UPLOAD_ERR_OK) {
        throw new Exception("Erro ao enviar a imagem.");
    }

    /* Limite de 5 MB */
    if ($arquivo["size"] > 5 * 1024 * 1024) {
        throw new Exception("A imagem não pode ter mais de 5 MB.");
    }

    /* Verifica se realmente é uma imagem */
    $info = @getimagesize($arquivo["tmp_name"]);

    if ($info === false) {
        throw new Exception("O arquivo enviado não é uma imagem válida.");
    }

    /* Extensões permitidas */
    $extensoesPermitidas = [
        "jpg",
        "jpeg",
        "png",
        "gif",
        "webp"
    ];

    $extensao = strtolower(
        pathinfo($arquivo["name"], PATHINFO_EXTENSION)
    );

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        throw new Exception(
            "Formato de imagem não permitido. Use JPG, JPEG, PNG, GIF ou WEBP."
        );
    }

    /*
     * Pasta onde as imagens dos produtos ficarão.
     * A pasta será criada automaticamente caso não exista.
     */
    $pasta = __DIR__ . "/imagens/produtos/";

    if (!is_dir($pasta)) {
        if (!mkdir($pasta, 0755, true)) {
            throw new Exception("Não foi possível criar a pasta de imagens.");
        }
    }

    /*
     * Nome único para evitar que uma imagem substitua outra.
     */
    $nomeArquivo = "produto_" . uniqid("", true) . "." . $extensao;

    $destino = $pasta . $nomeArquivo;

    if (!move_uploaded_file($arquivo["tmp_name"], $destino)) {
        throw new Exception("Não foi possível salvar a imagem no servidor.");
    }

    /*
     * Esse é o caminho que será salvo no banco.
     */
    return "imagens/produtos/" . $nomeArquivo;
}


/* =========================================================
   EXCLUIR ARQUIVO DE IMAGEM
========================================================= */

function excluirImagem($caminho) {

    if (empty($caminho)) {
        return;
    }

    /*
     * Só tentamos apagar imagens que estejam dentro
     * da pasta de produtos.
     */
    if (strpos($caminho, "imagens/produtos/") !== 0) {
        return;
    }

    $arquivo = __DIR__ . "/" . $caminho;

    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}


/* =========================================================
   BUSCAR PRODUTOS
========================================================= */

function buscar_produtos($conn) {

    $sql = "SELECT id, nome, descricao, preco, imagem, categoria, criado_em
            FROM produtos
            ORDER BY id DESC";

    $resultado = mysqli_query($conn, $sql);

    if (!$resultado) {
        throw new Exception(mysqli_error($conn));
    }

    return $resultado;
}


/* =========================================================
   AÇÕES DE PRODUTOS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        $acao = $_POST["acao"] ?? "";


        /* =====================================================
           ADICIONAR PRODUTO
        ===================================================== */

        if ($acao === "adicionar") {

            $nome = trim($_POST["nome"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");
            $preco = (float) str_replace(
                ",",
                ".",
                $_POST["preco"] ?? "0"
            );
            $categoria = trim($_POST["categoria"] ?? "");

            if ($nome === "") {
                throw new Exception("Informe o nome do produto.");
            }

            if ($preco < 0) {
                throw new Exception("O preço não pode ser negativo.");
            }

            /*
             * A imagem agora vem do computador.
             */
            $imagem = salvarImagem($_FILES["imagem"] ?? null);

            if ($imagem === "") {
                throw new Exception("Selecione uma imagem para o produto.");
            }

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO produtos
                (nome, descricao, preco, imagem, categoria)
                VALUES (?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssdss",
                $nome,
                $descricao,
                $preco,
                $imagem,
                $categoria
            );

            if (!mysqli_stmt_execute($stmt)) {

                /*
                 * Se o cadastro falhar, tenta apagar
                 * a imagem que acabou de ser enviada.
                 */
                excluirImagem($imagem);

                if (mysqli_errno($conn) == 1062) {
                    throw new Exception(
                        "Já existe um produto com esse nome."
                    );
                }

                throw new Exception(mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);

            $mensagem = "Produto cadastrado com sucesso!";
            $pagina = "produtos";
        }


        /* =====================================================
           EDITAR PRODUTO
        ===================================================== */

        if ($acao === "editar") {

            $id = (int)($_POST["id"] ?? 0);

            $nome = trim($_POST["nome"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");

            $preco = (float) str_replace(
                ",",
                ".",
                $_POST["preco"] ?? "0"
            );

            $categoria = trim($_POST["categoria"] ?? "");

            if ($id <= 0 || $nome === "") {
                throw new Exception("Dados do produto inválidos.");
            }

            if ($preco < 0) {
                throw new Exception("O preço não pode ser negativo.");
            }


            /*
             * Primeiro buscamos a imagem atual.
             */
            $stmtAtual = mysqli_prepare(
                $conn,
                "SELECT imagem FROM produtos WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmtAtual,
                "i",
                $id
            );

            mysqli_stmt_execute($stmtAtual);

            $resultadoAtual = mysqli_stmt_get_result($stmtAtual);

            $produtoAtual = mysqli_fetch_assoc($resultadoAtual);

            mysqli_stmt_close($stmtAtual);

            if (!$produtoAtual) {
                throw new Exception("Produto não encontrado.");
            }

            $imagemAtual = $produtoAtual["imagem"] ?? "";


            /*
             * Se o usuário enviou uma nova imagem,
             * substituímos a antiga.
             */
            $novaImagem = "";

            if (
                isset($_FILES["imagem"]) &&
                $_FILES["imagem"]["error"] !== UPLOAD_ERR_NO_FILE
            ) {
                $novaImagem = salvarImagem($_FILES["imagem"]);
            }


            if ($novaImagem !== "") {

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE produtos
                     SET nome = ?,
                         descricao = ?,
                         preco = ?,
                         imagem = ?,
                         categoria = ?
                     WHERE id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssdssi",
                    $nome,
                    $descricao,
                    $preco,
                    $novaImagem,
                    $categoria,
                    $id
                );

            } else {

                /*
                 * Sem nova imagem:
                 * mantém a imagem atual.
                 */
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE produtos
                     SET nome = ?,
                         descricao = ?,
                         preco = ?,
                         categoria = ?
                     WHERE id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssdsi",
                    $nome,
                    $descricao,
                    $preco,
                    $categoria,
                    $id
                );
            }


            try {

                if (!mysqli_stmt_execute($stmt)) {

                    if (mysqli_errno($conn) == 1062) {
                        throw new Exception(
                            "Já existe outro produto com esse nome."
                        );
                    }

                    throw new Exception(mysqli_error($conn));
                }

            } catch (Throwable $e) {

                /*
                 * Se a nova imagem já foi enviada mas o UPDATE
                 * falhou, apagamos a nova imagem.
                 */
                if ($novaImagem !== "") {
                    excluirImagem($novaImagem);
                }

                mysqli_stmt_close($stmt);

                throw $e;
            }

            mysqli_stmt_close($stmt);


            /*
             * Depois que o banco foi atualizado com sucesso,
             * podemos apagar a imagem antiga.
             */
            if ($novaImagem !== "") {
                excluirImagem($imagemAtual);
            }

            $mensagem = "Produto atualizado com sucesso!";
            $pagina = "produtos";
        }


        /* =====================================================
           EXCLUIR PRODUTO
        ===================================================== */

        if ($acao === "excluir") {

            $id = (int)($_POST["id"] ?? 0);

            if ($id <= 0) {
                throw new Exception("Produto inválido.");
            }


            /*
             * Busca a imagem antes de excluir o produto.
             */
            $stmt = mysqli_prepare(
                $conn,
                "SELECT imagem FROM produtos WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id
            );

            mysqli_stmt_execute($stmt);

            $resultado = mysqli_stmt_get_result($stmt);

            $produto = mysqli_fetch_assoc($resultado);

            mysqli_stmt_close($stmt);


            if (!$produto) {
                throw new Exception("Produto não encontrado.");
            }

            $imagem = $produto["imagem"] ?? "";


            /*
             * Exclui apenas o cadastro do produto.
             * Vendas antigas continuam preservadas.
             */
            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM produtos WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $id
            );

            mysqli_stmt_execute($stmt);

            $afetadas = mysqli_stmt_affected_rows($stmt);

            mysqli_stmt_close($stmt);


            if ($afetadas === 0) {
                throw new Exception("Produto não encontrado.");
            }


            /*
             * Agora que o produto foi excluído do banco,
             * apagamos a imagem enviada para o servidor.
             */
            excluirImagem($imagem);

            $mensagem = "Produto excluído com sucesso.";
            $pagina = "produtos";
        }

    } catch (Throwable $e) {

        $erro = $e->getMessage();

        $pagina = $_POST["retorno"] ?? "produtos";
    }
}


/* =========================================================
   DASHBOARD
========================================================= */

$totalProdutos = 0;
$totalPedidos = 0;
$faturamento = 0;
$maisVendido = null;

$ranking = [];
$ultimasVendas = [];
$produtosRecentes = [];


try {

    /* Total de produtos */

    $r = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM produtos"
    );

    $totalProdutos = (int)mysqli_fetch_assoc($r)["total"];


    /* Pedidos finalizados */

    $r = mysqli_query(
        $conn,
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(total),0) AS faturamento
         FROM pedidos
         WHERE status = 'finalizado'"
    );

    $dadosPedidos = mysqli_fetch_assoc($r);

    $totalPedidos = (int)$dadosPedidos["total"];

    $faturamento = (float)$dadosPedidos["faturamento"];


    /* =====================================================
       TABELA CARRINHO
    ===================================================== */

    $tabelaCarrinho = "carrinho";

    $check = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'carrinho'"
    );

    if (
        $check &&
        (int)mysqli_fetch_assoc($check)["total"] === 0
    ) {
        $tabelaCarrinho = "caarrinho";
    }


    /* Produtos mais vendidos */

    $r = mysqli_query(
        $conn,
        "SELECT
            nome_produto,
            SUM(quantidade) AS unidades,
            SUM(subtotal) AS valor
         FROM `$tabelaCarrinho`
         WHERE finalizado = 1
         GROUP BY nome_produto
         ORDER BY unidades DESC, valor DESC
         LIMIT 5"
    );

    while ($linha = mysqli_fetch_assoc($r)) {
        $ranking[] = $linha;
    }

    $maisVendido = $ranking[0] ?? null;


    /* Últimas vendas */

    $r = mysqli_query(
        $conn,
        "SELECT
            id,
            usuario_id,
            total,
            status,
            data_pedido
         FROM pedidos
         ORDER BY id DESC
         LIMIT 8"
    );

    while ($linha = mysqli_fetch_assoc($r)) {
        $ultimasVendas[] = $linha;
    }


    /* Produtos recentes */

    $r = mysqli_query(
        $conn,
        "SELECT
            id,
            nome,
            preco,
            categoria,
            imagem,
            criado_em
         FROM produtos
         ORDER BY id DESC
         LIMIT 8"
    );

    while ($linha = mysqli_fetch_assoc($r)) {
        $produtosRecentes[] = $linha;
    }

} catch (Throwable $e) {

    $erroDashboard = $e->getMessage();
}


/* =========================================================
   EDIÇÃO
========================================================= */

$produtoEditar = null;

if ($pagina === "editar") {

    try {

        $id = (int)($_GET["id"] ?? 0);

        if ($id <= 0) {
            throw new Exception("Produto inválido.");
        }

        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                id,
                nome,
                descricao,
                preco,
                imagem,
                categoria
             FROM produtos
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);

        $produtoEditar = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);


        if (!$produtoEditar) {
            throw new Exception("Produto não encontrado.");
        }

    } catch (Throwable $e) {

        $erro = $e->getMessage();

        $pagina = "produtos";
    }
}


/* =========================================================
   LISTA DE PRODUTOS
========================================================= */

try {

    $listaProdutos = buscar_produtos($conn);

} catch (Throwable $e) {

    $listaProdutos = false;

    if ($pagina === "produtos") {
        $erro = $e->getMessage();
    }
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Madison Versalit | Painel Administrativo</title>


<style>

*{
    box-sizing:border-box;
    margin:0;
    padding:0
}

body{
    font-family:Arial,Helvetica,sans-serif;
    background:#f7f3ef;
    color:#2b211b;
}

a{
    text-decoration:none;
    color:inherit
}

button,
input,
textarea,
select{
    font:inherit
}

.admin-layout{
    min-height:100vh
}

.sidebar{
    position:fixed;
    left:0;
    top:0;
    bottom:0;
    width:245px;
    background:#241a15;
    color:#fff;
    padding:28px 18px;
    z-index:10
}

.brand{
    padding:5px 13px 28px;
    border-bottom:1px solid rgba(255,255,255,.12);
    margin-bottom:22px
}

.brand h1{
    font-size:22px;
    letter-spacing:.3px
}

.brand span{
    font-size:11px;
    color:#d4a45f;
    letter-spacing:3px
}

.brand-mark{
    display:none
}

.menu{
    display:grid;
    gap:8px
}

.menu a{
    padding:13px 14px;
    border-radius:9px;
    color:#d9d0ca;
    transition:.2s
}

.menu a:hover,
.menu a.active{
    background:#8a541d;
    color:#fff
}

.sidebar-bottom{
    position:absolute;
    left:18px;
    right:18px;
    bottom:25px;
    display:grid;
    gap:8px
}

.main{
    margin-left:245px;
    width:calc(100% - 245px);
    padding:34px;
    max-width:1500px
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    margin-bottom:28px
}

.eyebrow{
    font-size:11px;
    letter-spacing:2px;
    color:#a16d34;
    margin-bottom:5px
}

.topbar h2{
    font-size:29px
}

.topbar p.sub{
    color:#806f63;
    margin-top:5px
}

.badge{
    background:#fff;
    padding:11px 15px;
    border-radius:30px;
    border:1px solid #eadfd5;
    font-size:13px
}

.cards{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:25px
}

.card{
    background:#fff;
    border:1px solid #eadfd5;
    border-radius:15px;
    padding:22px;
    box-shadow:0 6px 18px rgba(50,30,10,.04)
}

.card .label{
    color:#8b7b70;
    font-size:13px
}

.card strong{
    display:block;
    font-size:28px;
    margin-top:10px
}

.card small{
    color:#9b6a2e;
    display:block;
    margin-top:8px
}

.grid{
    display:grid;
    grid-template-columns:1fr 1.35fr;
    gap:20px
}

.panel{
    background:#fff;
    border:1px solid #eadfd5;
    border-radius:15px;
    padding:22px;
    box-shadow:0 6px 18px rgba(50,30,10,.04);
    margin-bottom:20px
}

.panel h3{
    margin-bottom:18px
}

.ranking{
    list-style:none;
    display:grid;
    gap:12px
}

.ranking li{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    padding:12px;
    border-radius:10px;
    background:#faf7f3
}

.rank-left{
    display:flex;
    align-items:center;
    gap:11px;
    min-width:0
}

.rank{
    width:32px;
    height:32px;
    display:grid;
    place-items:center;
    border-radius:50%;
    background:#ead5b8;
    font-weight:bold
}

.rank-name{
    font-weight:600;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap
}

.rank-meta{
    font-size:12px;
    color:#8b7b70;
    margin-top:3px
}

table{
    width:100%;
    border-collapse:collapse
}

th,
td{
    text-align:left;
    padding:13px 10px;
    border-bottom:1px solid #eee5dd;
    font-size:13px
}

th{
    color:#806f63;
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.5px
}

td.price{
    font-weight:700;
    white-space:nowrap
}

.empty{
    color:#8b7b70;
    padding:15px 0
}

.error{
    background:#fff0f0;
    color:#a33;
    border:1px solid #f1cccc;
    padding:13px 14px;
    border-radius:10px;
    margin-bottom:20px
}

.success{
    background:#eff8ef;
    color:#477347;
    border:1px solid #cfe4cf;
    padding:13px 14px;
    border-radius:10px;
    margin-bottom:20px
}

.btn{
    border:0;
    border-radius:9px;
    padding:10px 15px;
    cursor:pointer;
    font-weight:600
}

.btn-primary{
    background:#8a541d;
    color:#fff
}

.btn-primary:hover{
    background:#704315
}

.btn-light{
    background:#f5eee8;
    color:#5d4636
}

.btn-danger{
    background:#f7e6e3;
    color:#9a4e43
}

.actions{
    display:flex;
    gap:7px;
    flex-wrap:wrap
}

.product-thumb{
    width:48px;
    height:58px;
    border-radius:8px;
    background:#eee4da;
    object-fit:cover;
    vertical-align:middle;
    margin-right:8px
}

.product-cell{
    display:flex;
    align-items:center;
    gap:5px;
    min-width:230px
}

.status{
    display:inline-block;
    padding:5px 9px;
    border-radius:20px;
    background:#edf4ea;
    color:#638060;
    font-size:11px;
    font-weight:600
}

.form-panel{
    max-width:900px
}

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px
}

.field{
    display:grid;
    gap:7px
}

.field.full{
    grid-column:1/-1
}

.field label{
    font-size:13px;
    font-weight:600;
    color:#59483d
}

.field input,
.field textarea,
.field select{
    width:100%;
    padding:12px 13px;
    border:1px solid #dfd2c7;
    border-radius:9px;
    background:#fff;
    outline:none
}

.field input:focus,
.field textarea:focus,
.field select:focus{
    border-color:#a16d34;
    box-shadow:0 0 0 3px rgba(161,109,52,.1)
}

.field textarea{
    min-height:120px;
    resize:vertical
}

.form-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    margin-top:20px
}

.page-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:20px
}

.search-box{
    width:100%;
    max-width:360px;
    padding:11px 13px;
    border:1px solid #dfd2c7;
    border-radius:9px;
    margin-bottom:15px
}

.info{
    font-size:12px;
    color:#8b7b70;
    margin-bottom:18px
}

.imagem-atual{
    width:150px;
    height:190px;
    object-fit:cover;
    border-radius:12px;
    border:1px solid #eadfd5;
    display:block;
    margin-bottom:12px
}

.sem-imagem{
    width:150px;
    height:190px;
    border-radius:12px;
    background:#eee4da;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#8b7b70;
    font-size:13px;
    margin-bottom:12px
}

@media(max-width:1050px){

    .cards{
        grid-template-columns:repeat(2,1fr)
    }

    .grid{
        grid-template-columns:1fr
    }
}

@media(max-width:750px){

    .sidebar{
        width:75px;
        padding:25px 10px
    }

    .brand h1,
    .brand span,
    .sidebar-bottom a,
    .menu a{
        font-size:0
    }

    .brand-mark{
        display:block;
        font-size:20px;
        font-weight:700;
        text-align:center
    }

    .menu a{
        display:grid;
        place-items:center
    }

    .menu a::first-letter{
        font-size:18px
    }

    .main{
        margin-left:75px;
        width:calc(100% - 75px);
        padding:22px 16px
    }

    .cards{
        grid-template-columns:1fr
    }

    .form-grid{
        grid-template-columns:1fr
    }

    .field.full{
        grid-column:auto
    }

    .topbar h2{
        font-size:25px
    }

    .badge{
        display:none
    }

    .panel{
        padding:16px;
        overflow:auto
    }
}

</style>

</head>


<body>

<aside class="sidebar">

    <div class="brand">

        <div class="brand-mark">MV</div>

        <h1>Madison Versalit</h1>

        <span>PAINEL ADM</span>

    </div>


    <nav class="menu">

        <a href="admin.php?pagina=dashboard"
           class="<?= $pagina === 'dashboard' ? 'active' : '' ?>">
            ▦ &nbsp; Dashboard
        </a>

        <a href="admin.php?pagina=produtos"
           class="<?= in_array($pagina,['produtos','novo','editar']) ? 'active' : '' ?>">
            ◈ &nbsp; Produtos
        </a>

        <a href="admin.php?pagina=dashboard#vendas">
            □ &nbsp; Vendas
        </a>

        <a href="admin.php?pagina=dashboard#mais-vendidos">
            ↗ &nbsp; Mais vendidos
        </a>

    </nav>

<div class="sidebar-bottom">

    <a href="index.html">
        ↩ &nbsp; Voltar para a loja
    </a>

    <a href="admin_logout.php">
        🚪 &nbsp; Sair do painel
    </a>

</div>

</aside>


<main class="main">


<?php if ($mensagem): ?>

    <div class="success">
        <?= h($mensagem) ?>
    </div>

<?php endif; ?>


<?php if ($erro): ?>

    <div class="error">
        <?= h($erro) ?>
    </div>

<?php endif; ?>


<?php if ($pagina === "dashboard"): ?>


<header class="topbar">

    <div>

        <div class="eyebrow">
            VISÃO GERAL
        </div>

        <h2>
            Dashboard
        </h2>

        <p class="sub">
            Visão geral da sua loja em tempo real.
        </p>

    </div>

    <div class="badge">
        🛍️ Madison Versalit
    </div>

</header>


<?php if (!empty($erroDashboard)): ?>

<div class="error">
    Erro ao consultar o banco:
    <?= h($erroDashboard) ?>
</div>

<?php endif; ?>


<section class="cards">

    <div class="card">

        <span class="label">
            Produtos cadastrados
        </span>

        <strong>
            <?= $totalProdutos ?>
        </strong>

        <small>
            Na tabela produtos
        </small>

    </div>


    <div class="card">

        <span class="label">
            Pedidos finalizados
        </span>

        <strong>
            <?= $totalPedidos ?>
        </strong>

        <small>
            Vendas concluídas
        </small>

    </div>


    <div class="card">

        <span class="label">
            Faturamento
        </span>

        <strong>
            <?= moeda($faturamento) ?>
        </strong>

        <small>
            Pedidos finalizados
        </small>

    </div>


    <div class="card">

        <span class="label">
            Mais vendido
        </span>

        <strong>
            <?= $maisVendido
                ? h($maisVendido["nome_produto"])
                : "—"
            ?>
        </strong>

        <small>

            <?= $maisVendido
                ? (int)$maisVendido["unidades"] . " unidade(s)"
                : "Nenhuma venda registrada"
            ?>

        </small>

    </div>

</section>


<section class="grid">


<article class="panel" id="mais-vendidos">

    <h3>
        🔥 Produtos mais vendidos
    </h3>


    <?php if (!$ranking): ?>

        <div class="empty">
            Ainda não há vendas finalizadas.
        </div>

    <?php else: ?>

        <ol class="ranking">

        <?php foreach ($ranking as $i => $item): ?>

            <li>

                <div class="rank-left">

                    <span class="rank">
                        <?= $i + 1 ?>
                    </span>

                    <div>

                        <div class="rank-name">
                            <?= h($item["nome_produto"]) ?>
                        </div>

                        <div class="rank-meta">
                            <?= moeda($item["valor"]) ?> em vendas
                        </div>

                    </div>

                </div>

                <strong>
                    <?= (int)$item["unidades"] ?> un.
                </strong>

            </li>

        <?php endforeach; ?>

        </ol>

    <?php endif; ?>

</article>


<article class="panel" id="vendas">

    <h3>
        📦 Últimas vendas
    </h3>

    <div style="overflow:auto">

    <table>

        <thead>

            <tr>

                <th>Pedido</th>
                <th>Cliente</th>
                <th>Total</th>
                <th>Status</th>
                <th>Data</th>

            </tr>

        </thead>


        <tbody>

        <?php foreach ($ultimasVendas as $venda): ?>

            <tr>

                <td>
                    #<?= (int)$venda["id"] ?>
                </td>

                <td>
                    <?= h($venda["usuario_id"] ?: "—") ?>
                </td>

                <td class="price">
                    <?= moeda($venda["total"]) ?>
                </td>

                <td>

                    <span class="status">
                        <?= h($venda["status"]) ?>
                    </span>

                </td>

                <td>
                <?= h($venda["data_pedido"]) ?>
                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

    </div>

</article>

</section>


<section class="panel">

    <div class="page-head">

        <div>

            <div class="eyebrow">
                CATÁLOGO
            </div>

            <h3>
                Produtos cadastrados
            </h3>

        </div>


        <a class="btn btn-primary"
           href="admin.php?pagina=novo">
            + Novo produto
        </a>

    </div>


    <div style="overflow:auto">

    <table>

        <thead>

            <tr>

                <th>Produto</th>
                <th>Categoria</th>
                <th>Preço</th>
                <th>Cadastro</th>
                <th>Ações</th>

            </tr>

        </thead>


        <tbody>

        <?php foreach ($produtosRecentes as $produto): ?>

            <tr>

                <td>

                    <div class="product-cell">

                        <?php if (!empty($produto["imagem"])): ?>

                            <img
                                class="product-thumb"
                                src="<?= h($produto["imagem"]) ?>"
                                alt=""
                            >

                        <?php else: ?>

                            <div class="product-thumb"></div>

                        <?php endif; ?>


                        <strong>
                            <?= h($produto["nome"]) ?>
                        </strong>

                    </div>

                </td>


                <td>
                    <?= h($produto["categoria"] ?: "—") ?>
                </td>


                <td class="price">
                    <?= moeda($produto["preco"]) ?>
                </td>


                <td>
                    <?= h($produto["criado_em"]) ?>
                </td>


                <td>

                    <a
                        class="btn btn-light"
                        href="admin.php?pagina=editar&id=<?= (int)$produto["id"] ?>"
                    >
                        Editar
                    </a>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

    </div>

</section>


<?php elseif ($pagina === "produtos"): ?>


<header class="topbar">

    <div>

        <div class="eyebrow">
            CATÁLOGO
        </div>

        <h2>
            Produtos
        </h2>

        <p class="sub">
            Gerencie os produtos da Madison Versalit.
        </p>

    </div>


    <a
        class="btn btn-primary"
        href="admin.php?pagina=novo"
    >
        + Novo produto
    </a>

</header>


<section class="panel">


<input
    id="buscarProduto"
    class="search-box"
    type="search"
    placeholder="🔎 Buscar produto..."
>


<div class="info">

    Os produtos abaixo são carregados diretamente
    da tabela <strong>produtos</strong>.

</div>


<div style="overflow:auto">

<table id="tabelaProdutos">

<thead>

<tr>

    <th>Produto</th>
    <th>Categoria</th>
    <th>Preço</th>
    <th>Data</th>
    <th>Ações</th>

</tr>

</thead>


<tbody>


<?php if ($listaProdutos): ?>


<?php while ($produto = mysqli_fetch_assoc($listaProdutos)): ?>


<tr class="linha-produto">


<td>

<div class="product-cell">


<?php if (!empty($produto["imagem"])): ?>

<img
    class="product-thumb"
    src="<?= h($produto["imagem"]) ?>"
    alt=""
>

<?php else: ?>

<div class="product-thumb"></div>

<?php endif; ?>


<div>

<strong>
    <?= h($produto["nome"]) ?>
</strong>


<?php if (!empty($produto["descricao"])): ?>

<div class="rank-meta">
    <?= h($produto["descricao"]) ?>
</div>

<?php endif; ?>

</div>

</div>

</td>


<td>
    <?= h($produto["categoria"] ?: "—") ?>
</td>


<td class="price">
    <?= moeda($produto["preco"]) ?>
</td>


<td>
    <?= h($produto["criado_em"]) ?>
</td>


<td>

<div class="actions">


<a
    class="btn btn-light"
    href="admin.php?pagina=editar&id=<?= (int)$produto["id"] ?>"
>
    ✏️ Editar
</a>


<form
    method="POST"
    onsubmit="return confirm('Tem certeza que deseja excluir este produto?');"
>

<input
    type="hidden"
    name="acao"
    value="excluir"
>

<input
    type="hidden"
    name="retorno"
    value="produtos"
>

<input
    type="hidden"
    name="id"
    value="<?= (int)$produto["id"] ?>"
>


<button
    class="btn btn-danger"
    type="submit"
>
    🗑️ Excluir
</button>

</form>


</div>

</td>


</tr>


<?php endwhile; ?>


<?php endif; ?>


</tbody>

</table>

</div>

</section>


<script>

const busca = document.getElementById("buscarProduto");

busca?.addEventListener("input", function () {

    const termo = this.value.toLowerCase().trim();

    document.querySelectorAll(".linha-produto").forEach(linha => {

        linha.style.display =
            linha.innerText
                .toLowerCase()
                .includes(termo)
                ? ""
                : "none";

    });

});

</script>


<?php elseif ($pagina === "novo"): ?>


<header class="topbar">

    <div>

        <div class="eyebrow">
            CATÁLOGO
        </div>

        <h2>
            Novo produto
        </h2>

        <p class="sub">
            Cadastre um novo produto diretamente no banco.
        </p>

    </div>

</header>


<section class="panel form-panel">


<form
    method="POST"
    enctype="multipart/form-data"
>


<input
    type="hidden"
    name="acao"
    value="adicionar"
>


<input
    type="hidden"
    name="retorno"
    value="produtos"
>


<div class="form-grid">


<div class="field full">

    <label>
        Nome do produto *
    </label>

    <input
        name="nome"
        required
        maxlength="150"
        placeholder="Ex.: Vestido longo verde"
    >

</div>


<div class="field">

    <label>
        Preço *
    </label>

    <input
        name="preco"
        required
        inputmode="decimal"
        placeholder="129,90"
    >

</div>


<div class="field">

    <label>
        Categoria
    </label>

    <select name="categoria">

        <option value="">
            Selecione
        </option>

        <option>
            Feminino
        </option>

        <option>
            Masculino
        </option>

        <option>
            Infantil
        </option>

        <option>
            Unissex
        </option>

        <option>
            Acessórios
        </option>

    </select>

</div>


<div class="field full">

    <label>
        Imagem do produto *
    </label>

    <input
        type="file"
        name="imagem"
        accept="image/jpeg,image/png,image/gif,image/webp"
        required
    >

    <small style="color:#8b7b70">
        Escolha uma imagem do seu computador.
        Máximo: 5 MB.
    </small>

</div>


<div class="field full">

    <label>
        Descrição
    </label>

    <textarea
        name="descricao"
        placeholder="Descreva o produto..."
    ></textarea>

</div>


</div>


<div class="form-actions">

    <a
        class="btn btn-light"
        href="admin.php?pagina=produtos"
    >
        Cancelar
    </a>


    <button
        class="btn btn-primary"
        type="submit"
    >
        Cadastrar produto
    </button>

</div>


</form>

</section>


<?php elseif ($pagina === "editar" && $produtoEditar): ?>


<header class="topbar">

    <div>

        <div class="eyebrow">
            CATÁLOGO
        </div>

        <h2>
            Editar produto
        </h2>

        <p class="sub">
            Altere os dados do produto selecionado.
        </p>

    </div>

</header>


<section class="panel form-panel">


<form
    method="POST"
    enctype="multipart/form-data"
>


<input
    type="hidden"
    name="acao"
    value="editar"
>


<input
    type="hidden"
    name="retorno"
    value="produtos"
>


<input
    type="hidden"
    name="id"
    value="<?= (int)$produtoEditar["id"] ?>"
>


<div class="form-grid">


<div class="field full">

    <label>
        Nome do produto *
    </label>

    <input
        name="nome"
        required
        maxlength="150"
        value="<?= h($produtoEditar["nome"]) ?>"
    >

</div>


<div class="field">

    <label>
        Preço *
    </label>

    <input
        name="preco"
        required
        inputmode="decimal"
        value="<?= h($produtoEditar["preco"]) ?>"
    >

</div>


<div class="field">

    <label>
        Categoria
    </label>

    <input
        name="categoria"
        maxlength="60"
        value="<?= h($produtoEditar["categoria"]) ?>"
    >

</div>


<div class="field full">

    <label>
        Imagem do produto
    </label>


    <?php if (!empty($produtoEditar["imagem"])): ?>

        <img
            class="imagem-atual"
            src="<?= h($produtoEditar["imagem"]) ?>"
            alt="Imagem atual do produto"
        >

        <small style="color:#8b7b70;margin-bottom:10px;display:block;">
            Imagem atual
        </small>

    <?php else: ?>

        <div class="sem-imagem">
            Sem imagem
        </div>

    <?php endif; ?>


    <input
        type="file"
        name="imagem"
        accept="image/jpeg,image/png,image/gif,image/webp"
    >


    <small style="color:#8b7b70">

        Se quiser trocar a imagem,
        escolha um novo arquivo.
        Se deixar vazio, a imagem atual será mantida.

    </small>

</div>


<div class="field full">

    <label>
        Descrição
    </label>

    <textarea name="descricao"><?= h($produtoEditar["descricao"]) ?></textarea>

</div>


</div>


<div class="form-actions">

    <a
        class="btn btn-light"
        href="admin.php?pagina=produtos"
    >
        Cancelar
    </a>


    <button
        class="btn btn-primary"
        type="submit"
    >
        Salvar alterações
    </button>

</div>


</form>

</section>


<?php endif; ?>


</main>


</body>

</html>