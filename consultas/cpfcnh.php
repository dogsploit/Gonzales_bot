<?php
/**
 * consultas/cnh.php — versão PRO DEFINITIVA
 * Bloqueio 15 minutos + checker corrigido
 * Desenvolvido por Gonzales ⚡
 */

error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* ===========================================================
   IDENTIFICAÇÃO ÚNICA DO USUÁRIO
   =========================================================== */
function getUserUniqueId(): string {
    if (!empty($GLOBALS['user_id'])) return 'tg_' . intval($GLOBALS['user_id']);
    if (!empty($GLOBALS['from']['id'])) return 'tg_' . intval($GLOBALS['from']['id']);
    if (!empty($GLOBALS['chat_id'])) return 'chat_' . intval($GLOBALS['chat_id']);
    if (!empty($GLOBALS['username'])) return 'usr_' . preg_replace('/\W+/', '', strtolower($GLOBALS['username']));
    return 'anon_' . substr(sha1(($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 12);
}

/* ===========================================================
   BLOQUEIO CONSULTA REPETIDA (15 MINUTOS)
   =========================================================== */
function checkConsultaRepetida(string $tipo, string $valor, int $ttl = 300): string|false {
    $uid = getUserUniqueId();
    $dir = sys_get_temp_dir() . "/cache_consultas_individuais";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $file = "{$dir}/{$uid}_{$tipo}.json";
    $now  = time();
    $cache = [];

    if (file_exists($file)) {
        $cache = json_decode(file_get_contents($file), true) ?: [];
    }

    if (isset($cache[$valor])) {
        $elapsed   = $now - $cache[$valor];
        $remaining = $ttl - $elapsed;

        if ($remaining > 0) {
            $min = floor($remaining / 60);
            $sec = $remaining % 60;

            $tempo = $min > 0
                ? "{$min} min " . ($sec > 0 ? "{$sec} seg" : "")
                : "{$sec} seg";

            return "⚠️ <b>Sistema temporariamente bloqueado</b>\n\n"
                 . "Por motivos de segurança, não é permitido repetir consultas para o mesmo CPF dentro de um intervalo de tempo.\n\n"
                 . "Este CPF já possui uma consulta recente registrada na <b>Base de Dados CNH</b>.\n\n"
                 . "⏳ <b>Liberação automática em:</b> <code>{$tempo}</code>\n\n"
                 . "<i>Outros CPFs podem ser consultados normalmente durante este período.</i>";
        }
    }

    $cache[$valor] = $now;

    foreach ($cache as $k => $t) {
        if (($now - $t) > $ttl) unset($cache[$k]);
    }

    file_put_contents($file, json_encode($cache), LOCK_EX);
    return false;
}

/* ===========================================================
   VALIDAÇÃO
   =========================================================== */
if (!isset($ARG)) return "⚠️ <b>Erro interno na consulta CNH.</b>";

$cpf = preg_replace('/\D+/', '', $ARG);
if (strlen($cpf) !== 11) return "⚠️ <b>CPF inválido!</b>";

if ($msg = checkConsultaRepetida('cnh', $cpf)) return $msg;

/* ===========================================================
   CONSULTA API
   =========================================================== */
$endpoint = "https://meuvpsbr.shop/apis/serpr00o.php?apikey=gonzales&string=" . urlencode($cpf);
$canal = "https://t.me/GonzalesCanal";

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* ===========================================================
   CHECKER CORRIGIDO
   =========================================================== */

// Falha real de conexão
if ($err || !$res || $code === 0) {
    return "⚠️ <b>Servidor no momento encontra-se indisponível.</b>\n"
         . "<i>Tente novamente mais tarde.</i>";
}

$data = json_decode($res, true);

// JSON inválido
if (!is_array($data)) {
    return "⚠️ <b>Erro inesperado no servidor.</b>";
}

// ⚠️ CPF NÃO POSSUI CNH
if (
    (isset($data['message']) && stripos($data['message'], 'não encontrado') !== false) ||
    (isset($data['code']) && (int)$data['code'] === 500 && empty($data['cnh']))
) {
    return "⚠️ <b>CNH não encontrada</b>\n"
         . "<i>Ou a Pessoa não possui CNH</i>";
}

// ⚠️ SEM CNH
if (empty($data['cnh'])) {
    return "⚠️ <b>CNH não encontrada</b>\n\n"
         . "O CPF informado não possui CNH cadastrada.";
}

/* ===========================================================
   HELPERS
   =========================================================== */
$h = function ($v) {
    if ($v === null) return "<code>SEM INFORMAÇÃO</code>";
    if (is_array($v)) {
        if (empty($v)) return "<code>SEM INFORMAÇÃO</code>";
        $v = implode(', ', $v);
    }
    $v = trim((string)$v);
    if ($v === '' || in_array(strtolower($v), ['null','undefined','-','0','99','inexistente'], true)) {
        return "<code>SEM INFORMAÇÃO</code>";
    }
    return "<code>" . htmlspecialchars(mb_strtoupper($v, 'UTF-8'), ENT_QUOTES, 'UTF-8') . "</code>";
};

$formatDate = function ($v) {
    if (!$v) return "<code>SEM INFORMAÇÃO</code>";
    try {
        return "<code>" . (new DateTime($v))->format('d/m/Y') . "</code>";
    } catch (Exception $e) {
        return "<code>SEM INFORMAÇÃO</code>";
    }
};

$idade = function ($v) {
    if (!$v) return '';
    try {
        return " <code>(" . (new DateTime($v))->diff(new DateTime())->y . " anos)</code>";
    } catch (Exception $e) {
        return '';
    }
};

/* ===========================================================
   RESPOSTA
   =========================================================== */
$txt  = "🕵️ <b>CONSULTA DE CNH COMPLETA</b> 🕵️\n\n";

/* DADOS PESSOAIS */
$txt .= "• <b>DADOS PESSOAIS</b>\n\n";
$txt .= "• <b>Nome:</b> " . $h($data['nome']) . "\n";
$txt .= "• <b>CPF:</b> " . $h($data['cpf']) . "\n";
$txt .= "• <b>Sexo:</b> " . $h($data['descricaoSexo']) . "\n";
$txt .= "• <b>Nascimento:</b> " . $formatDate($data['dataNascimento']) . $idade($data['dataNascimento']) . "\n";
$txt .= "• <b>Nacionalidade:</b> " . $h($data['descricaoNacionalidade']) . "\n";
$txt .= "• <b>Naturalidade:</b> " . $h($data['descricaoLocalidadeNascimento']) . "\n\n";

/* FILIAÇÃO */
$txt .= "• <b>FILIAÇÃO</b>\n\n";
$txt .= "• <b>Mãe:</b> " . $h($data['nomeMae']) . "\n";
$txt .= "• <b>Pai:</b> " . $h($data['nomePai']) . "\n\n";

/* ENDEREÇO */
$txt .= "• <b>ENDEREÇO</b>\n\n";
$txt .= "• <b>Logradouro:</b> " . $h($data['enderecoLogradouro']) . "\n";
$txt .= "• <b>Número:</b> " . $h($data['enderecoNumero']) . "\n";
$txt .= "• <b>Complemento:</b> " . $h($data['enderecoComplemento']) . "\n";
$txt .= "• <b>Bairro:</b> " . $h($data['enderecoBairro']) . "\n";
$txt .= "• <b>CEP:</b> " . $h($data['enderecoCep']) . "\n";
$txt .= "• <b>Cidade:</b> " . $h($data['descricaoEnderecoMunicipio']) . "\n";
$txt .= "• <b>UF:</b> " . $h($data['enderecoUf']) . "\n\n";

/* DOCUMENTOS */
$txt .= "• <b>DOCUMENTOS</b>\n\n";
$txt .= "• <b>Documento:</b> " . $h($data['descricaoDocumento']) . "\n";
$txt .= "• <b>Nº:</b> " . $h($data['numeroDocumento']) . "\n";
$txt .= "• <b>Órgão:</b> " . $h($data['orgaoExpedidorDocumento']) . "\n";
$txt .= "• <b>UF:</b> " . $h($data['ufExpedidorDocumento']) . "\n\n";

/* CNH */
$txt .= "• <b>DADOS DA CNH</b>\n\n";
$txt .= "• <b>Nº Registro:</b> " . $h($data['numeroRegistro']) . "\n";
$txt .= "• <b>Nº CNH:</b> " . $h($data['numeroFormularioCnh']) . "\n";
$txt .= "• <b>RENACH:</b> " . $h($data['numeroFormularioRenach']) . "\n";
$txt .= "• <b>Categoria Atual:</b> " . $h($data['categoriaAtual']) . "\n";
$txt .= "• <b>Categoria Autorizada:</b> " . $h($data['categoriaAutorizada']) . "\n";
$txt .= "• <b>Categoria Rebaixada:</b> " . $h($data['categoriaRebaixada']) . "\n";
$txt .= "• <b>Permissionário:</b> " . ((int)$data['permissionario'] === 1 ? "<code>SIM</code>" : "<code>NÃO</code>") . "\n";
$txt .= "• <b>Situação CNH:</b> " . $h($data['descricaoSituacaoCnh']) . "\n";
$txt .= "• <b>Situação Anterior:</b> " . $h($data['descricaoSituacaoCnhAnterior']) . "\n";
$txt .= "• <b>Validade:</b> " . $formatDate($data['dataValidadeCnh']) . "\n";
$txt .= "• <b>Primeira Habilitação:</b> " . $formatDate($data['dataPrimeiraHabilitacao']) . "\n";
$txt .= "• <b>UF 1ª Habilitação:</b> " . $h($data['ufPrimeiraHabilitacao']) . "\n";
$txt .= "• <b>Última Emissão:</b> " . $formatDate($data['dataUltimaEmissaoHistorico']) . "\n\n";

/* CURSOS */
$txt .= "• <b>CURSOS</b>\n\n";
$txt .= "• <b>TPP:</b> " . $h($data['descricaoClassificacaoCursoTpp']) . "\n";
$txt .= "• <b>TE:</b> " . $h($data['descricaoClassificacaoCursoTe']) . "\n";
$txt .= "• <b>TCP:</b> " . $h($data['descricaoClassificacaoCursoTcp']) . "\n";
$txt .= "• <b>TCI:</b> " . $h($data['descricaoClassificacaoCursoTci']) . "\n";
$txt .= "• <b>TMT:</b> " . $h($data['descricaoClassificacaoCursoTmt']) . "\n";
$txt .= "• <b>TMF:</b> " . $h($data['descricaoClassificacaoCursoTmf']) . "\n";
$txt .= "• <b>TVE:</b> " . $h($data['descricaoClassificacaoCursoTve']) . "\n\n";

/* RESTRIÇÕES */
$txt .= "• <b>RESTRIÇÕES / OBSERVAÇÕES</b>\n\n";
$txt .= "• <b>Restrições Médicas:</b> " . $h($data['restricoesMedicas']) . "\n";
$txt .= "• <b>Observações:</b> " . $h($data['quadroObservacoesCnh']) . "\n";
$txt .= "• <b>Ocorrências:</b> " . $h($data['quantidadeOcorrenciasImpedimentos']) . "\n\n";

/* SISTEMA */
$txt .= "• <b>SISTEMA</b>\n\n";
$txt .= "• <b>UF Domínio:</b> " . $h($data['ufDominio']) . "\n";
$txt .= "• <b>Serviço:</b> " . $h($data['servicoConsultado']) . "\n";

return $txt;