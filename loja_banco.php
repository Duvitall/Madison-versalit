<?php
// Arquivo de funcoes reutilizaveis da loja.
// Ele concentra a parte de JSON, validacao e INSERT/SELECT no banco.

// Envia uma resposta JSON padronizada para o JavaScript.
function responder_json($ok, $dados = [], $status = 200) {
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(array_merge(["ok" => $ok], $dados), JSON_UNESCAPED_UNICODE);
    exit;
}

// Le o corpo da requisicao quando o JavaScript envia JSON pelo fetch().
function ler_json() {
    $conteudo = file_get_contents("php://input");
    if ($conteudo === false || trim($conteudo) === "") {
        return [];
    }

    $dados = json_decode($conteudo, true);
    return is_array($dados) ? $dados : [];
}

// Protege nomes de tabela/coluna antes de colocar em uma query SQL.
// So aceita letras, numeros e underline para evitar SQL Injection em identificadores.
function identificador_sql($nome) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $nome)) {
        throw new Exception("Nome de tabela ou coluna invalido.");
    }

    return "`" . $nome . "`";
}

// Confere se uma tabela existe no banco atual.
function tabela_existe($conn, $tabela) {
    $sql = "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $tabela);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);

    return (int) $total > 0;
}

// O projeto ja teve duvida entre caarrinho e carrinho.
// Esta funcao escolhe a tabela que realmente existe no banco.
function nome_tabela_carrinho($conn) {
    if (tabela_existe($conn, "caarrinho")) {
        return "caarrinho";
    }

    if (tabela_existe($conn, "carrinho")) {
        return "carrinho";
    }

    return "caarrinho";
}

// Retorna um mapa com as colunas reais de uma tabela.
// Exemplo: ajuda a descobrir se a coluna se chama quantidade ou quantidades.
function colunas_tabela($conn, $tabela) {
    $resultado = mysqli_query($conn, "DESCRIBE " . identificador_sql($tabela));
    $colunas = [];

    while ($campo = mysqli_fetch_assoc($resultado)) {
        $colunas[strtolower($campo["Field"])] = $campo["Field"];
    }

    return $colunas;
}

// Procura a primeira coluna existente dentro de uma lista de nomes possiveis.
function coluna_existente($colunas, $opcoes) {
    foreach ($opcoes as $opcao) {
        $chave = strtolower($opcao);
        if (isset($colunas[$chave])) {
            return $colunas[$chave];
        }
    }

    return null;
}

// Verifica se uma coluna id gera numeros automaticamente.
function coluna_tem_auto_increment($conn, $tabela, $coluna) {
    $resultado = mysqli_query($conn, "DESCRIBE " . identificador_sql($tabela));

    while ($campo = mysqli_fetch_assoc($resultado)) {
        if (strtolower($campo["Field"]) === strtolower($coluna)) {
            return stripos($campo["Extra"], "auto_increment") !== false;
        }
    }

    return false;
}

// Se o id nao for AUTO_INCREMENT, calcula manualmente o proximo numero.
function proximo_id($conn, $tabela, $coluna) {
    $sql = "SELECT COALESCE(MAX(" . identificador_sql($coluna) . "), 0) + 1 AS proximo FROM " . identificador_sql($tabela);
    $resultado = mysqli_query($conn, $sql);
    $linha = mysqli_fetch_assoc($resultado);

    return (int) $linha["proximo"];
}

// Funcao de apoio do instalador antigo. Mantida para testes, mas o fluxo normal nao depende dela.
function adicionar_coluna_se_precisar($conn, $tabela, &$colunas, $nome, $definicao) {
    if (!isset($colunas[strtolower($nome)])) {
        mysqli_query(
            $conn,
            "ALTER TABLE " . identificador_sql($tabela) .
            " ADD COLUMN " . identificador_sql($nome) . " " . $definicao
        );

        $colunas[strtolower($nome)] = $nome;
    }
}

// Funcao de apoio para criar/ajustar tabelas automaticamente.
// Hoje o site funciona com as tabelas que voce ja criou no phpMyAdmin.
function garantir_tabelas_loja($conn) {
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS produtos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(150) NOT NULL,
            descricao TEXT NULL,
            preco DECIMAL(10,2) NOT NULL DEFAULT 0,
            imagem VARCHAR(255) NULL,
            categoria VARCHAR(60) NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_produtos_nome (nome)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS pedidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            usuario_nome VARCHAR(150) NULL,
            usuario_email VARCHAR(150) NULL,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'finalizado',
            data_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $tabelaCarrinho = nome_tabela_carrinho($conn);
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS " . identificador_sql($tabelaCarrinho) . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NULL,
            produto_id INT NULL,
            usuario_id INT NULL,
            usuario_nome VARCHAR(150) NULL,
            usuario_email VARCHAR(150) NULL,
            nome_produto VARCHAR(150) NOT NULL,
            preco DECIMAL(10,2) NOT NULL DEFAULT 0,
            quantidade INT NOT NULL DEFAULT 1,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            finalizado TINYINT(1) NOT NULL DEFAULT 0,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $colunasProdutos = colunas_tabela($conn, "produtos");
    adicionar_coluna_se_precisar($conn, "produtos", $colunasProdutos, "nome", "VARCHAR(150) NOT NULL DEFAULT ''");
    adicionar_coluna_se_precisar($conn, "produtos", $colunasProdutos, "descricao", "TEXT NULL");
    adicionar_coluna_se_precisar($conn, "produtos", $colunasProdutos, "preco", "DECIMAL(10,2) NOT NULL DEFAULT 0");
    adicionar_coluna_se_precisar($conn, "produtos", $colunasProdutos, "imagem", "VARCHAR(255) NULL");
    adicionar_coluna_se_precisar($conn, "produtos", $colunasProdutos, "categoria", "VARCHAR(60) NULL");
    adicionar_coluna_se_precisar($conn, "produtos", $colunasProdutos, "criado_em", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

    $colunasPedidos = colunas_tabela($conn, "pedidos");
    adicionar_coluna_se_precisar($conn, "pedidos", $colunasPedidos, "usuario_id", "INT NULL");
    adicionar_coluna_se_precisar($conn, "pedidos", $colunasPedidos, "usuario_nome", "VARCHAR(150) NULL");
    adicionar_coluna_se_precisar($conn, "pedidos", $colunasPedidos, "usuario_email", "VARCHAR(150) NULL");
    adicionar_coluna_se_precisar($conn, "pedidos", $colunasPedidos, "total", "DECIMAL(10,2) NOT NULL DEFAULT 0");
    adicionar_coluna_se_precisar($conn, "pedidos", $colunasPedidos, "status", "VARCHAR(30) NOT NULL DEFAULT 'finalizado'");
    adicionar_coluna_se_precisar($conn, "pedidos", $colunasPedidos, "data_pedido", "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");

    $colunasCarrinho = colunas_tabela($conn, $tabelaCarrinho);
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "pedido_id", "INT NULL");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "produto_id", "INT NULL");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "usuario_id", "INT NULL");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "usuario_nome", "VARCHAR(150) NULL");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "usuario_email", "VARCHAR(150) NULL");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "nome_produto", "VARCHAR(150) NOT NULL DEFAULT ''");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "preco", "DECIMAL(10,2) NOT NULL DEFAULT 0");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "quantidade", "INT NOT NULL DEFAULT 1");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "subtotal", "DECIMAL(10,2) NOT NULL DEFAULT 0");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "finalizado", "TINYINT(1) NOT NULL DEFAULT 0");
    adicionar_coluna_se_precisar($conn, $tabelaCarrinho, $colunasCarrinho, "criado_em", "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// Converte preco escrito como "129,90" ou "129.90" para numero decimal.
function preco_para_decimal($valor) {
    if (is_int($valor) || is_float($valor)) {
        return (float) $valor;
    }

    $texto = preg_replace('/[^0-9,.\-]/', '', (string) $valor);

    if (strpos($texto, ",") !== false && strpos($texto, ".") !== false) {
        $texto = str_replace(".", "", $texto);
        $texto = str_replace(",", ".", $texto);
    } elseif (strpos($texto, ",") !== false) {
        $texto = str_replace(",", ".", $texto);
    }

    return round((float) $texto, 2);
}

// Extrai os dados do usuario recebidos do JavaScript.
function usuario_do_payload($dados) {
    $usuario = isset($dados["usuario"]) && is_array($dados["usuario"]) ? $dados["usuario"] : $dados;

    return [
        "id" => isset($usuario["id"]) && $usuario["id"] !== "" ? (int) $usuario["id"] : null,
        "nome" => trim($usuario["nome"] ?? ""),
        "email" => trim($usuario["email"] ?? "")
    ];
}

// Extrai os dados do produto recebidos do JavaScript.
function produto_do_payload($dados) {
    $produto = isset($dados["produto"]) && is_array($dados["produto"]) ? $dados["produto"] : $dados;

    return [
        "nome" => trim($produto["nome"] ?? ""),
        "preco" => preco_para_decimal($produto["preco"] ?? 0),
        "descricao" => trim($produto["descricao"] ?? ""),
        "imagem" => trim($produto["imagem"] ?? ""),
        "categoria" => trim($produto["categoria"] ?? ""),
        "quantidade" => max(1, (int) ($produto["quantidade"] ?? 1))
    ];
}

// Decide o tipo dos parametros para mysqli_stmt_bind_param.
// i = inteiro, d = decimal, s = texto.
function tipos_params($params) {
    $tipos = "";

    foreach ($params as $valor) {
        if (is_int($valor)) {
            $tipos .= "i";
        } elseif (is_float($valor)) {
            $tipos .= "d";
        } else {
            $tipos .= "s";
        }
    }

    return $tipos;
}

// Executa SQL com prepared statement para proteger os valores enviados ao banco.
function executar_preparado($conn, $sql, $params = []) {
    $stmt = mysqli_prepare($conn, $sql);

    if (!empty($params)) {
        $tipos = tipos_params($params);
        $refs = [$tipos];

        foreach ($params as $indice => $valor) {
            $refs[] = &$params[$indice];
        }

        call_user_func_array([$stmt, "bind_param"], $refs);
    }

    mysqli_stmt_execute($stmt);
    return $stmt;
}

// Adiciona um valor no INSERT somente se a coluna existir na tabela.
function adicionar_valor_coluna(&$dados, $colunas, $opcoes, $valor) {
    $coluna = coluna_existente($colunas, $opcoes);

    if ($coluna !== null && $valor !== null && $valor !== "") {
        $dados[$coluna] = $valor;
    }
}

// Insere uma linha em qualquer tabela, usando os nomes de coluna encontrados.
function inserir_linha($conn, $tabela, $dados) {
    if (empty($dados)) {
        return 0;
    }

    $colunas = colunas_tabela($conn, $tabela);
    $colunaId = coluna_existente($colunas, ["id", "codigo"]);
    $idManual = null;

    if ($colunaId !== null && !array_key_exists($colunaId, $dados) && !coluna_tem_auto_increment($conn, $tabela, $colunaId)) {
        $idManual = proximo_id($conn, $tabela, $colunaId);
        $dados = array_merge([$colunaId => $idManual], $dados);
    }

    $nomes = array_keys($dados);
    $campos = array_map("identificador_sql", $nomes);
    $marcadores = array_fill(0, count($nomes), "?");
    $valores = array_values($dados);

    $sql = "INSERT INTO " . identificador_sql($tabela) .
        " (" . implode(", ", $campos) . ") VALUES (" . implode(", ", $marcadores) . ")";

    executar_preparado($conn, $sql, $valores);

    return $idManual ?? mysqli_insert_id($conn);
}

// Procura um produto pelo nome. Se nao existir, cadastra na tabela produtos.
function salvar_ou_buscar_produto($conn, $produto) {
    if ($produto["nome"] === "") {
        throw new Exception("Produto sem nome.");
    }

    $tabela = "produtos";
    $colunas = colunas_tabela($conn, $tabela);
    $colunaNome = coluna_existente($colunas, ["nome", "nome_produto", "produto", "titulo"]);
    $colunaId = coluna_existente($colunas, ["id", "id_produto", "produto_id", "codigo", "cod_produto"]);

    if ($colunaNome !== null) {
        $stmt = executar_preparado(
            $conn,
            "SELECT * FROM " . identificador_sql($tabela) .
            " WHERE " . identificador_sql($colunaNome) . " = ? LIMIT 1",
            [$produto["nome"]]
        );
        $resultado = mysqli_stmt_get_result($stmt);
        $linha = mysqli_fetch_assoc($resultado);

        if ($linha) {
            return [
                "id" => $colunaId !== null && isset($linha[$colunaId]) ? (int) $linha[$colunaId] : null,
                "nome" => $produto["nome"]
            ];
        }
    }

    $dados = [];
    adicionar_valor_coluna($dados, $colunas, ["nome", "nome_produto", "produto", "titulo"], $produto["nome"]);
    adicionar_valor_coluna($dados, $colunas, ["preco", "valor", "preco_produto"], $produto["preco"]);
    adicionar_valor_coluna($dados, $colunas, ["descricao", "detalhes"], $produto["descricao"]);
    adicionar_valor_coluna($dados, $colunas, ["imagem", "foto", "img", "url_imagem"], $produto["imagem"]);
    adicionar_valor_coluna($dados, $colunas, ["categoria", "tipo"], $produto["categoria"]);

    $id = inserir_linha($conn, $tabela, $dados);

    return ["id" => $id ?: null, "nome" => $produto["nome"]];
}

// Cria uma linha na tabela pedidos para registrar a compra finalizada.
function criar_pedido($conn, $usuario, $total) {
    $tabela = "pedidos";
    $colunas = colunas_tabela($conn, $tabela);
    $dados = [];

    adicionar_valor_coluna($dados, $colunas, ["usuario_id", "id_usuario"], $usuario["id"]);
    adicionar_valor_coluna($dados, $colunas, ["usuario_nome", "nome_usuario", "cliente"], $usuario["nome"]);
    adicionar_valor_coluna($dados, $colunas, ["usuario_email", "email_usuario", "email"], $usuario["email"]);
    adicionar_valor_coluna($dados, $colunas, ["total", "valor_total", "preco_total"], $total);
    adicionar_valor_coluna($dados, $colunas, ["status", "situacao"], "finalizado");
    adicionar_valor_coluna($dados, $colunas, ["data_pedido", "data", "criado_em"], date("Y-m-d H:i:s"));

    return inserir_linha($conn, $tabela, $dados);
}

// Registra o produto escolhido na tabela carrinho.
function registrar_item_carrinho($conn, $usuario, $produto, $produtoSalvo, $pedidoId = null, $finalizado = false) {
    $tabela = nome_tabela_carrinho($conn);
    $colunas = colunas_tabela($conn, $tabela);
    $quantidade = max(1, (int) $produto["quantidade"]);
    $subtotal = round($produto["preco"] * $quantidade, 2);
    $dados = [];

    adicionar_valor_coluna($dados, $colunas, ["pedido_id", "id_pedido"], $pedidoId);
    adicionar_valor_coluna($dados, $colunas, ["produto_id", "id_produto"], $produtoSalvo["id"] ?? null);
    adicionar_valor_coluna($dados, $colunas, ["usuario_id", "id_usuario"], $usuario["id"]);
    adicionar_valor_coluna($dados, $colunas, ["usuario_nome", "nome_usuario", "cliente"], $usuario["nome"]);
    adicionar_valor_coluna($dados, $colunas, ["usuario_email", "email_usuario", "email"], $usuario["email"]);
    adicionar_valor_coluna($dados, $colunas, ["nome_produto", "produto", "nome"], $produto["nome"]);
    adicionar_valor_coluna($dados, $colunas, ["preco", "valor", "preco_unitario"], $produto["preco"]);
    adicionar_valor_coluna($dados, $colunas, ["quantidade", "quantidades", "qtd", "qtde"], $quantidade);
    adicionar_valor_coluna($dados, $colunas, ["subtotal", "total", "valor_total"], $subtotal);
    adicionar_valor_coluna($dados, $colunas, ["criado_em", "data", "data_carrinho"], date("Y-m-d H:i:s"));

    $colunaFinalizado = coluna_existente($colunas, ["finalizado", "comprado"]);
    if ($colunaFinalizado !== null) {
        $dados[$colunaFinalizado] = $finalizado ? 1 : 0;
    }

    $colunaStatus = coluna_existente($colunas, ["status", "situacao"]);
    if ($colunaStatus !== null) {
        $dados[$colunaStatus] = $finalizado ? "finalizado" : "aberto";
    }

    return inserir_linha($conn, $tabela, $dados);
}

// Funcao mantida para bancos que possuem campos como pedido_id/finalizado no carrinho.
function finalizar_carrinho_aberto($conn, $usuario, $pedidoId) {
    $tabela = nome_tabela_carrinho($conn);
    $colunas = colunas_tabela($conn, $tabela);
    $sets = [];
    $params = [];

    $colunaPedido = coluna_existente($colunas, ["pedido_id", "id_pedido"]);
    if ($colunaPedido !== null) {
        $sets[] = identificador_sql($colunaPedido) . " = ?";
        $params[] = $pedidoId;
    }

    $colunaFinalizado = coluna_existente($colunas, ["finalizado", "comprado"]);
    if ($colunaFinalizado !== null) {
        $sets[] = identificador_sql($colunaFinalizado) . " = ?";
        $params[] = 1;
    }

    $colunaStatus = coluna_existente($colunas, ["status", "situacao"]);
    if ($colunaStatus !== null) {
        $sets[] = identificador_sql($colunaStatus) . " = ?";
        $params[] = "finalizado";
    }

    $where = [];
    $colunaUsuarioId = coluna_existente($colunas, ["usuario_id", "id_usuario"]);
    $colunaUsuarioNome = coluna_existente($colunas, ["usuario_nome", "nome_usuario", "cliente"]);

    if ($colunaUsuarioId !== null && $usuario["id"] !== null) {
        $where[] = identificador_sql($colunaUsuarioId) . " = ?";
        $params[] = $usuario["id"];
    } elseif ($colunaUsuarioNome !== null && $usuario["nome"] !== "") {
        $where[] = identificador_sql($colunaUsuarioNome) . " = ?";
        $params[] = $usuario["nome"];
    }

    if ($colunaFinalizado === null && $colunaStatus === null) {
        return 0;
    }

    if ($colunaFinalizado !== null) {
        $where[] = identificador_sql($colunaFinalizado) . " = 0";
    } elseif ($colunaStatus !== null) {
        $where[] = identificador_sql($colunaStatus) . " = 'aberto'";
    }

    if (empty($sets) || empty($where)) {
        return 0;
    }

    $sql = "UPDATE " . identificador_sql($tabela) .
        " SET " . implode(", ", $sets) .
        " WHERE " . implode(" AND ", $where);

    $stmt = executar_preparado($conn, $sql, $params);
    return mysqli_stmt_affected_rows($stmt);
}

// Remove itens abertos do carrinho quando o banco tiver coluna de status/finalizado.
function limpar_carrinho_aberto($conn, $usuario) {
    $tabela = nome_tabela_carrinho($conn);
    $colunas = colunas_tabela($conn, $tabela);
    $where = [];
    $params = [];

    $colunaUsuarioId = coluna_existente($colunas, ["usuario_id", "id_usuario"]);
    $colunaUsuarioNome = coluna_existente($colunas, ["usuario_nome", "nome_usuario", "cliente"]);

    if ($colunaUsuarioId !== null && $usuario["id"] !== null) {
        $where[] = identificador_sql($colunaUsuarioId) . " = ?";
        $params[] = $usuario["id"];
    } elseif ($colunaUsuarioNome !== null && $usuario["nome"] !== "") {
        $where[] = identificador_sql($colunaUsuarioNome) . " = ?";
        $params[] = $usuario["nome"];
    }

    $colunaFinalizado = coluna_existente($colunas, ["finalizado", "comprado"]);
    $colunaStatus = coluna_existente($colunas, ["status", "situacao"]);

    if ($colunaFinalizado !== null) {
        $where[] = identificador_sql($colunaFinalizado) . " = 0";
    } elseif ($colunaStatus !== null) {
        $where[] = identificador_sql($colunaStatus) . " = 'aberto'";
    }

    if (empty($where)) {
        return 0;
    }

    $sql = "DELETE FROM " . identificador_sql($tabela) . " WHERE " . implode(" AND ", $where);
    $stmt = executar_preparado($conn, $sql, $params);

    return mysqli_stmt_affected_rows($stmt);
}
?>
