<?php
// Este arquivo e usado pela pagina registro.html.
// Ele busca no banco os pedidos do usuario logado e devolve em JSON.
error_reporting(E_ALL);
ini_set('display_errors', 0);

include("conexao.php");
include("loja_banco.php");

try {
    // Os dados do usuario chegam pela URL, enviados pelo JavaScript de registro.html.
    $usuario = [
        "id" => isset($_GET["usuario_id"]) && $_GET["usuario_id"] !== "" ? (int) $_GET["usuario_id"] : null,
        "nome" => trim($_GET["usuario_nome"] ?? ""),
        "email" => trim($_GET["usuario_email"] ?? "")
    ];

    // Descobre os nomes reais das colunas na tabela pedidos.
    // Isso ajuda o codigo a funcionar mesmo se a coluna se chamar id ou pedido_id, por exemplo.
    $colunasPedidos = colunas_tabela($conn, "pedidos");
    $colunaPedidoId = coluna_existente($colunasPedidos, ["id", "id_pedido", "pedido_id"]);
    $colunaData = coluna_existente($colunasPedidos, ["data_pedido", "data", "criado_em"]);
    $colunaTotal = coluna_existente($colunasPedidos, ["total", "valor_total", "preco_total"]);

    // Sem coluna de id nao da para identificar cada pedido.
    if ($colunaPedidoId === null) {
        responder_json(true, ["pedidos" => []]);
    }

    // Monta o filtro para retornar somente pedidos do cliente logado.
    $where = [];
    $params = [];
    $colunaUsuarioId = coluna_existente($colunasPedidos, ["usuario_id", "id_usuario"]);
    $colunaUsuarioNome = coluna_existente($colunasPedidos, ["usuario_nome", "nome_usuario", "cliente"]);
    $colunaUsuarioEmail = coluna_existente($colunasPedidos, ["usuario_email", "email_usuario", "email"]);

    if ($colunaUsuarioId !== null && $usuario["id"] !== null) {
        $where[] = identificador_sql($colunaUsuarioId) . " = ?";
        $params[] = $usuario["id"];
    } elseif ($colunaUsuarioEmail !== null && $usuario["email"] !== "") {
        $where[] = identificador_sql($colunaUsuarioEmail) . " = ?";
        $params[] = $usuario["email"];
    } elseif ($colunaUsuarioNome !== null && $usuario["nome"] !== "") {
        $where[] = identificador_sql($colunaUsuarioNome) . " = ?";
        $params[] = $usuario["nome"];
    }

    if (empty($where)) {
        responder_json(true, ["pedidos" => []]);
    }

    // Monta o SELECT dos pedidos. Alguns nomes de coluna sao dinamicos porque o banco pode variar.
    $selectData = $colunaData !== null ? identificador_sql($colunaData) : "NULL";
    $selectTotal = $colunaTotal !== null ? identificador_sql($colunaTotal) : "0";
    $sql = "SELECT " . identificador_sql($colunaPedidoId) . " AS pedido_id, " .
        $selectData . " AS data_pedido, " .
        $selectTotal . " AS total FROM " . identificador_sql("pedidos");

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY " . identificador_sql($colunaPedidoId) . " DESC LIMIT 30";

    $stmt = executar_preparado($conn, $sql, $params);
    $resultado = mysqli_stmt_get_result($stmt);
    $pedidos = [];

    // Se a tabela carrinho tiver pedido_id, tambem tenta buscar os itens de cada pedido.
    // No seu banco atual, o historico ja mostra o pedido mesmo quando nao ha item ligado por pedido_id.
    $tabelaCarrinho = nome_tabela_carrinho($conn);
    $colunasCarrinho = colunas_tabela($conn, $tabelaCarrinho);
    $colunaPedidoCarrinho = coluna_existente($colunasCarrinho, ["pedido_id", "id_pedido"]);
    $colunaNomeProduto = coluna_existente($colunasCarrinho, ["nome_produto", "produto", "nome"]);
    $colunaPreco = coluna_existente($colunasCarrinho, ["preco", "valor", "preco_unitario"]);
    $colunaQuantidade = coluna_existente($colunasCarrinho, ["quantidade", "quantidades", "qtd", "qtde"]);

    while ($pedido = mysqli_fetch_assoc($resultado)) {
        $itens = [];

        if ($colunaPedidoCarrinho !== null && $colunaNomeProduto !== null) {
            $selectPreco = $colunaPreco !== null ? identificador_sql($colunaPreco) : "0";
            $selectQuantidade = $colunaQuantidade !== null ? identificador_sql($colunaQuantidade) : "1";

            $stmtItens = executar_preparado(
                $conn,
                "SELECT " . identificador_sql($colunaNomeProduto) . " AS nome, " .
                $selectPreco . " AS preco, " .
                $selectQuantidade . " AS quantidade FROM " . identificador_sql($tabelaCarrinho) .
                " WHERE " . identificador_sql($colunaPedidoCarrinho) . " = ?",
                [(int) $pedido["pedido_id"]]
            );
            $resultadoItens = mysqli_stmt_get_result($stmtItens);

            while ($item = mysqli_fetch_assoc($resultadoItens)) {
                $itens[] = [
                    "nome" => $item["nome"],
                    "preco" => (float) $item["preco"],
                    "quantidade" => (int) $item["quantidade"]
                ];
            }
        }

        // Formato final enviado para o JavaScript.
        $pedidos[] = [
            "id" => (int) $pedido["pedido_id"],
            "data" => $pedido["data_pedido"],
            "total" => (float) $pedido["total"],
            "itens" => $itens
        ];
    }

    responder_json(true, ["pedidos" => $pedidos]);
} catch (Throwable $erro) {
    // Qualquer erro volta como JSON para a pagina decidir o que mostrar.
    responder_json(false, ["erro" => $erro->getMessage()], 500);
}
?>
