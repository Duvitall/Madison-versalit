<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

try {

    $sql = "
        SELECT
            id,
            nome,
            descricao,
            preco,
            imagem,
            categoria
        FROM produtos
        ORDER BY id DESC
    ";

    $resultado = mysqli_query($conn, $sql);

    if (!$resultado) {
        throw new Exception(mysqli_error($conn));
    }

    $produtos = [];

    while ($produto = mysqli_fetch_assoc($resultado)) {

        $produtos[] = [
            "id" => (int)$produto["id"],
            "nome" => $produto["nome"],
            "descricao" => $produto["descricao"],
            "preco" => (float)$produto["preco"],
            "imagem" => $produto["imagem"],
            "categoria" => $produto["categoria"]
        ];
    }

    echo json_encode([
        "ok" => true,
        "produtos" => $produtos
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "erro" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>