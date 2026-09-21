<?php

// Este arquivo e chamado quando o usuario clica em "Finalizar Compra".
// Ele soma os produtos do carrinho, cria o pedido e registra
// cada produto vendido na tabela carrinho.

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Importa conexao e funcoes da loja.
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/loja_banco.php";

/** @var mysqli $conn */

try {

    // Recebe o JSON enviado pelo carrinho.html.
    $dados = ler_json();

    $usuario = usuario_do_payload($dados);
    $itens = $dados["itens"] ?? [];

    // Nao cria pedido se o carrinho estiver vazio.
    if (!is_array($itens) || count($itens) === 0) {
        responder_json(false, ["erro" => "Carrinho vazio."], 400);
    }

    // Nao cria pedido sem usuario.
    if (
        $usuario["id"] === null &&
        $usuario["nome"] === "" &&
        $usuario["email"] === ""
    ) {
        responder_json(false, ["erro" => "Usuario nao identificado."], 400);
    }

    // Valida os itens e calcula o total do pedido.
    $produtos = [];
    $total = 0;

    foreach ($itens as $item) {

        if (!is_array($item)) {
            continue;
        }

        $produto = produto_do_payload($item);

        if ($produto["nome"] === "") {
            continue;
        }

        $produtos[] = $produto;

        $total += $produto["preco"] * $produto["quantidade"];
    }

    if (count($produtos) === 0) {
        responder_json(
            false,
            ["erro" => "Nenhum produto valido no carrinho."],
            400
        );
    }

    // ---------------------------------------------------------
    // 1. CRIA O PEDIDO
    // ---------------------------------------------------------

    $pedidoId = criar_pedido(
        $conn,
        $usuario,
        round($total, 2)
    );

    // ---------------------------------------------------------
    // 2. REGISTRA CADA PRODUTO VENDIDO
    // ---------------------------------------------------------

    foreach ($produtos as $produto) {

        // Garante que o produto exista na tabela produtos
        // e recupera o ID dele.
        $produtoSalvo = salvar_ou_buscar_produto(
            $conn,
            $produto
        );

        // Registra o item na tabela carrinho como
        // uma venda finalizada.
        registrar_item_carrinho(
            $conn,
            $usuario,
            $produto,
            $produtoSalvo,
            $pedidoId,
            true
        );
    }

    // ---------------------------------------------------------
    // 3. DEVOLVE A RESPOSTA PARA O JAVASCRIPT
    // ---------------------------------------------------------

    responder_json(true, [
        "pedido_id" => $pedidoId,
        "total" => round($total, 2),
        "itens" => count($produtos),
        "tabela_carrinho" => nome_tabela_carrinho($conn)
    ]);

} catch (Throwable $erro) {

    // Erros voltam em JSON para o carrinho.html mostrar
    // uma mensagem clara.
    responder_json(
        false,
        ["erro" => $erro->getMessage()],
        500
    );
}

?>