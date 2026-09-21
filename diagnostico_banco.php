<?php
// Pagina de apoio para apresentacao/testes.
// Ela nao altera o banco: apenas mostra tabelas e colunas existentes.
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("conexao.php");

// Confere se uma tabela existe no banco atual.
function tabela_existe_diagnostico($conn, $tabela) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    mysqli_stmt_bind_param($stmt, "s", $tabela);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);

    return (int) $total > 0;
}

// Mostra a estrutura de uma tabela: campo, tipo, chave e informacoes extras.
function mostrar_tabela($conn, $tabela) {
    echo "<h3>Tabela: " . htmlspecialchars($tabela) . "</h3>";

    if (!tabela_existe_diagnostico($conn, $tabela)) {
        echo "<p>Nao encontrada.</p>";
        return;
    }

    $resultado = mysqli_query($conn, "DESCRIBE `" . $tabela . "`");

    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrao</th><th>Extra</th></tr>";

    while ($campo = mysqli_fetch_assoc($resultado)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($campo["Field"]) . "</td>";
        echo "<td>" . htmlspecialchars($campo["Type"]) . "</td>";
        echo "<td>" . htmlspecialchars($campo["Null"]) . "</td>";
        echo "<td>" . htmlspecialchars($campo["Key"]) . "</td>";
        echo "<td>" . htmlspecialchars((string) $campo["Default"]) . "</td>";
        echo "<td>" . htmlspecialchars($campo["Extra"]) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
}

echo "<h2>Diagnostico do banco</h2>";
echo "<p>Conexao OK. Este arquivo apenas le a estrutura das tabelas.</p>";

// Lista todas as tabelas do banco para verificar se os nomes estao corretos.
$resultado = mysqli_query($conn, "SHOW TABLES");

echo "<h3>Todas as tabelas encontradas</h3>";
echo "<ul>";

while ($tabela = mysqli_fetch_array($resultado)) {
    echo "<li>" . htmlspecialchars($tabela[0]) . "</li>";
}

echo "</ul>";

// Mostra as tabelas principais usadas pelo projeto.
mostrar_tabela($conn, "usuarios");
mostrar_tabela($conn, "produtos");
mostrar_tabela($conn, "pedidos");
mostrar_tabela($conn, "carrinho");
mostrar_tabela($conn, "caarrinho");
?>
