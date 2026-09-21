<?php
// Este arquivo recebe a lista de produtos que esta no index.html.
// Ele garante que esses produtos tambem existam na tabela produtos.
error_reporting(E_ALL);
ini_set('display_errors', 0);

include("conexao.php");
include("loja_banco.php");

try {
    // Le o JSON enviado pela funcao sincronizarProdutos() no index.html.
    $dados = ler_json();
    $produtos = $dados["produtos"] ?? [];

    // A lista precisa ser um array de produtos.
    if (!is_array($produtos)) {
        responder_json(false, ["erro" => "Lista de produtos invalida."], 400);
    }

    $salvos = 0;

    // Percorre cada produto e salva somente os itens validos.
    foreach ($produtos as $item) {
        if (!is_array($item)) {
            continue;
        }

        $produto = produto_do_payload($item);
        if ($produto["nome"] === "") {
            continue;
        }

        salvar_ou_buscar_produto($conn, $produto);
        $salvos++;
    }

    // Retorna quantos produtos foram processados.
    responder_json(true, ["produtos" => $salvos]);
} catch (Throwable $erro) {
    responder_json(false, ["erro" => $erro->getMessage()], 500);
}
?>
