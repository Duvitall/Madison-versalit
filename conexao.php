<?php
// Dados de acesso ao banco MySQL hospedado na InfinityFree.
// Todos os outros arquivos PHP usam este arquivo para reaproveitar a mesma conexao.
$host = "sql105.infinityfree.com";
$user = "if0_41913946";
$pass = "dudabr291209";
$db = "if0_41913946_madisonversalit";

// Faz o mysqli mostrar erros como excecoes. Isso facilita descobrir problemas no banco.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Abre a conexao com o banco e define UTF-8 para aceitar textos com acentos.
    $conn = mysqli_connect($host, $user, $pass, $db);
    mysqli_set_charset($conn, "utf8mb4");
} catch (mysqli_sql_exception $erro) {
    // Se a conexao falhar, o site para aqui e mostra a mensagem do erro.
    die("Erro na conexao com o banco de dados: " . $erro->getMessage());
}
?>
