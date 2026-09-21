<?php
// Este arquivo e chamado quando o usuario clica em "Comprar".
// Ele salva o produto escolhido na tabela carrinho.
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Conexao com o banco e funcoes reutilizaveis da loja.
include("conexao.php");
include("loja_banco.php");

try {
    // Le o JSON enviado pelo fetch() do index.html.
    $dados = ler_json();
    $usuario = usuario_do_payload($dados);
    $produto = produto_do_payload($dados);

    // Garante que o produto enviado tem nome.
    if ($produto["nome"] === "") {
        responder_json(false, ["erro" => "Produto sem nome."], 400);
    }

    // Garante que existe algum usuario logado antes de salvar no carrinho.
    if ($usuario["id"] === null && $usuario["nome"] === "" && $usuario["email"] === "") {
        responder_json(false, ["erro" => "Usuario nao identificado."], 400);
    }

    // Primeiro garante que o produto existe na tabela produtos.
    // Depois grava a relacao usuario + produto na tabela carrinho.
    $produtoSalvo = salvar_ou_buscar_produto($conn, $produto);
    $itemId = registrar_item_carrinho($conn, $usuario, $produto, $produtoSalvo, null, false);

    // Resposta em JSON para o JavaScript saber que deu certo.
    responder_json(true, [
        "produto_id" => $produtoSalvo["id"],
        "item_id" => $itemId,
        "tabela_carrinho" => nome_tabela_carrinho($conn)
    ]);
} catch (Throwable $erro) {
    // Se algo der errado, devolve o erro para aparecer no alert do navegador.
    responder_json(false, ["erro" => $erro->getMessage()], 500);
}
?>
