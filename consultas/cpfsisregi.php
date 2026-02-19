<?php
// consultas/cpfsisregi.php
error_reporting(0);
date_default_timezone_set('America/Sao_Paulo');

/* ================= CONFIG ================= */
$API_URL = "https://meuvpsbr.shop/siregi/cpf.php?cpf=";
$CONNECT_TIMEOUT = 3;
$TOTAL_TIMEOUT   = 8;

/* ================= VALIDAÇÃO ================= */
$cpfArg = isset($ARG) ? (string)$ARG : '';
$cpfClean = preg_replace('/\D+/', '', $cpfArg);
if (strlen($cpfClean) !== 11 || preg_match('/(\d)\1{10}/', $cpfClean)) {
    return "⚠️ <b>CPF inválido!</b>";
}
$cpfFmt = substr($cpfClean,0,3).'.'.substr($cpfClean,3,3).'.'.substr($cpfClean,6,3).'-'.substr($cpfClean,9,2);

/* ================= HELPERS ================= */
function esc($v): string {
    if (!isset($v) || $v === '' || $v === null) return 'Sem informação';
    $v = trim((string)$v);
    $invalid = ['null','undefined','desconhecido','sem informação','---','nenhum','nenhuma'];
    if (in_array(strtolower($v), $invalid, true)) return 'Sem informação';
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function call_api($url, $connectTimeout, $totalTimeout) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT        => $totalTimeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return null;
    $json = json_decode($resp, true);
    return is_array($json) ? $json : null;
}

function idade($data) {
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $data, $m)) return '';
    try {
        $d = new DateTime("{$m[3]}-{$m[2]}-{$m[1]}");
        $t = new DateTime('today');
        return $d->diff($t)->y . " anos";
    } catch (Throwable $e) {
        return '';
    }
}

/* ================= API REQUEST ================= */
$data = call_api($API_URL . $cpfClean, $CONNECT_TIMEOUT, $TOTAL_TIMEOUT);

// 🚨 Caso não haja retorno, erro de formato, erro na API ou dados vazios
if (
    !$data ||
    !is_array($data) ||
    isset($data['erro']) ||
    empty($data['dados']) ||
    !isset($data['status']) ||
    strtolower($data['status']) !== 'sucesso' ||
    empty($data['dados']['Dados pessoais'])
) {
    return "⚠️ <b>CPF NÃO ENCONTRADO!</b>";
}

/* ================= EXTRAI OS DADOS ================= */
$dados       = $data['dados']['Dados pessoais'] ?? [];
$obito       = $data['dados']['Detalhes do Óbito'] ?? [];
$contatos    = $data['dados']['Contatos'] ?? [];
$documentos  = $data['dados']['Documentos'] ?? [];
$endereco    = $data['dados']['Endereço'] ?? [];
$rodape      = $data['rodape'] ?? [];

/* ================= CAMPOS BÁSICOS ================= */
$cns   = esc($dados['CNS'] ?? '');
$nome  = esc($dados['Nome'] ?? '');
$apelido = esc($dados['Nome Social / Apelido'] ?? '');
$sexo  = esc($dados['Sexo'] ?? '');
$raca  = esc($dados['Raça'] ?? '');
$etnia = esc($dados['Etnia Indígena'] ?? '');
$nasc  = esc($dados['Data de Nascimento'] ?? '');
$tipoSangue = esc($dados['Tipo Sanguíneo'] ?? '');
$nacionalidade = esc($dados['Nacionalidade'] ?? '');
$munNasc = esc($dados['Município de Nascimento'] ?? '');
$tipoMoradia = esc($dados['Tipo de Moradia'] ?? '');
$idadeTxt = idade($nasc);
if ($idadeTxt) $nasc .= " ({$idadeTxt})";

/* ================= AFILIAÇÃO ================= */
$mae = esc($dados['Nome da Mãe'] ?? '');
$pai = esc($dados['Nome do Pai'] ?? '');

/* ================= MONTAR RESULTADO ================= */
$txt  = "🕵️ <b>CONSULTA DE CPF (SISREG-III)</b> 🕵️\n\n";
$txt .= "° <b>CPF:</b> <code>{$cpfFmt}</code>\n";
$txt .= "° <b>CNS:</b> <code>{$cns}</code>\n";
$txt .= "° <b>NOME:</b> <code>{$nome}</code>\n";
$txt .= "° <b>SEXO:</b> <code>{$sexo}</code>\n";
$txt .= "° <b>RAÇA:</b> <code>{$raca}</code>\n";
$txt .= "° <b>ETNIA INDÍGENA:</b> <code>{$etnia}</code>\n";
$txt .= "° <b>DATA DE NASCIMENTO:</b> <code>{$nasc}</code>\n";
$txt .= "° <b>TIPO SANGUÍNEO:</b> <code>{$tipoSangue}</code>\n\n";
$txt .= "° <b>NACIONALIDADE:</b> <code>{$nacionalidade}</code>\n";
$txt .= "° <b>MUNICÍPIO DE NASCIMENTO:</b> <code>{$munNasc}</code>\n";
$txt .= "° <b>TIPO DE MORADIA:</b> <code>{$tipoMoradia}</code>\n\n";

/* ================= AFILIAÇÃO ================= */
$txt .= "° <b>AFILIAÇÃO:</b>\n\n";
$txt .= "° <b>MÃE:</b> <code>{$mae}</code>\n";
$txt .= "° <b>PAI:</b> <code>{$pai}</code>\n\n";

/* ================= ÓBITO ================= */
$txt .= "° <b>ÓBITO:</b>\n\n";
if (!empty($obito['Data de Óbito'])) {
    $txt .= "<code>Registro de óbito encontrado</code>\n";
    foreach ($obito as $k => $v) {
        $txt .= "° <b>{$k}:</b> <code>" . esc($v) . "</code>\n";
    }
} elseif (!empty($obito['mensagem']) && stripos($obito['mensagem'], 'nenhum detalhe') !== false) {
    $txt .= "<code>Pessoa está viva</code>\n";
} else {
    $txt .= "<code>Sem informação</code>\n";
}
$txt .= "\n";

/* ================= TELEFONES ================= */
$txt .= "° <b>TELEFONES:</b>\n\n";
if (!empty($contatos['Telefones']) && is_array($contatos['Telefones'])) {
    $temTelefone = false;
    foreach ($contatos['Telefones'] as $t) {
        $ddd  = trim($t['DDD'] ?? '');
        $num  = trim($t['Número'] ?? '');
        $tipo = strtoupper(trim($t['Tipo Telefone'] ?? ''));

        if ($num !== '' && strtolower($num) !== 'sem informação') {
            $temTelefone = true;
            $dddFmt = $ddd !== '' ? "{$ddd}" : '';
            $txt .= "<code>({$tipo}) {$dddFmt} {$num}</code>\n";
        }
    }
    if (!$temTelefone) $txt .= "<code>Sem informação</code>\n";
} else {
    $txt .= "<code>Sem informação</code>\n";
}
$txt .= "\n";

/* ================= E-MAILS ================= */
$txt .= "° <b>E-MAILS:</b>\n\n";
if (!empty($contatos['E-mails']) && is_array($contatos['E-mails'])) {
    $temEmail = false;
    foreach ($contatos['E-mails'] as $e) {
        if (isset($e['E-mail'])) {
            $email = esc($e['E-mail']);
            if (strtolower($email) !== 'sem informação') {
                $txt .= "<code>{$email}</code>\n";
                $temEmail = true;
            }
        } elseif (is_string($e) && $e !== '') {
            $txt .= "<code>" . esc($e) . "</code>\n";
            $temEmail = true;
        }
    }
    if (!$temEmail) $txt .= "<code>Sem informação</code>\n";
} elseif (isset($contatos['E-mails']['mensagem'])) {
    $msg = strtolower(trim($contatos['E-mails']['mensagem']));
    if (str_contains($msg, 'nenhum')) $txt .= "<code>Sem informação</code>\n";
    else $txt .= "<code>" . esc($contatos['E-mails']['mensagem']) . "</code>\n";
} else {
    $txt .= "<code>Sem informação</code>\n";
}
$txt .= "\n";

/* ================= DOCUMENTOS ================= */
$txt .= "° <b>DOCUMENTOS:</b>\n\n";
if (!empty($documentos['Identidade'])) {
    $rg = $documentos['Identidade'];
    $txt .= "° <b>RG:</b> <code>" . esc($rg['Num. RG'] ?? '') . "</code>\n";
    $txt .= "° <b>ÓRGÃO EMISSOR:</b> <code>" . esc($rg['Órgão Emissor'] ?? '') . "</code>\n";
    $txt .= "° <b>ESTADO EMISSOR:</b> <code>" . esc($rg['Estado Emissor'] ?? '') . "</code>\n";
    $txt .= "° <b>DATA DE EMISSÃO:</b> <code>" . esc($rg['Data de Emissão'] ?? '') . "</code>\n";
} elseif (isset($documentos['mensagem'])) {
    $txt .= "<code>Sem informação</code>\n";
} else {
    $txt .= "<code>Sem informação</code>\n";
}
$txt .= "\n";

/* ================= CERTIDÃO DE NASCIMENTO ================= */
$txt .= "° <b>CERTIDÃO DE NASCIMENTO:</b>\n\n";
if (!empty($documentos['Certidão de Nascimento (Antiga)'])) {
    $c = $documentos['Certidão de Nascimento (Antiga)'];
    $txt .= "° <b>CARTÓRIO:</b> <code>" . esc($c['Nome do Cartório'] ?? '') . "</code>\n";
    $txt .= "° <b>LIVRO:</b> <code>" . esc($c['Livro'] ?? '') . "</code>\n";
    $txt .= "° <b>FOLHA:</b> <code>" . esc($c['Folha'] ?? '') . "</code>\n";
    $txt .= "° <b>TERMO:</b> <code>" . esc($c['Termo'] ?? '') . "</code>\n";
    $txt .= "° <b>DATA DE EMISSÃO:</b> <code>" . esc($c['Data de Emissão'] ?? '') . "</code>\n";
} else {
    $txt .= "<code>Sem informação</code>\n";
}
$txt .= "\n";

/* ================= ENDEREÇO ================= */
$txt .= "° <b>ENDEREÇO:</b>\n\n";
if (!empty($endereco['mensagem'])) {
    $txt .= "<code>Sem informação</code>\n";
} else {
    $txt .= "° <b>TIPO DE LOGRADOURO:</b> <code>" . esc($endereco['Tipo Logradouro'] ?? '') . "</code>\n";
    $txt .= "° <b>LOGRADOURO:</b> <code>" . esc($endereco['Logradouro'] ?? '') . "</code>\n";
    $txt .= "° <b>COMPLEMENTO:</b> <code>" . esc($endereco['Complemento'] ?? '') . "</code>\n";
    $txt .= "° <b>NÚMERO:</b> <code>" . esc($endereco['Número'] ?? '') . "</code>\n";
    $txt .= "° <b>BAIRRO:</b> <code>" . esc($endereco['Bairro'] ?? '') . "</code>\n";
    $txt .= "° <b>MUNICÍPIO DE RESIDÊNCIA:</b> <code>" . esc($endereco['Município de Residência'] ?? '') . "</code>\n";
    $txt .= "° <b>CEP:</b> <code>" . esc($endereco['CEP'] ?? '') . "</code>\n";
    $txt .= "° <b>PAÍS:</b> <code>" . esc($endereco['País de Residência'] ?? '') . "</code>\n";
}
$txt .= "\n";

/* ================= RODAPÉ ================= */
$txt .= "° <b>INFORMAÇÕES ADICIONAIS:</b>\n\n";
if (!empty($rodape)) {
    foreach ($rodape as $r) {
        $txt .= "<code>" . esc($r) . "</code>\n";
    }
} else {
    $txt .= "<code>Sem informação</code>\n";
}
$txt .= "\n";

return $txt;
?>