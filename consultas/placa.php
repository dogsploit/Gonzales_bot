<?php
/**
 * consultas/placa.php — versão PRO com API Chacal Mods
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
    return 'anon_' . substr(sha1(($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 12);
}

/* ===========================================================
   BLOQUEIO CONSULTA REPETIDA (30 MINUTOS / MESMA PLACA)
   =========================================================== */
function checkConsultaRepetida(string $tipo, string $valor, int $ttl = 1800): string|false {
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
        $remaining = $ttl - ($now - $cache[$valor]);
        if ($remaining > 0) {
            $min = floor($remaining / 60);
            $sec = $remaining % 60;
            $tempo = "{$min} min {$sec} seg";

            return "🚫 <b>Sistema temporariamente bloqueado</b>\n\n"
                 . "Esta placa já possui uma consulta recente registrada.\n\n"
                 . "⏳ <b>Liberação automática em:</b> <code>{$tempo}</code>\n\n"
                 . "<i>Outras placas podem ser consultadas normalmente.</i>";
        }
    }

    $cache[$valor] = $now;
    file_put_contents($file, json_encode($cache), LOCK_EX);
    return false;
}

/* ===========================================================
   VALIDAÇÃO
   =========================================================== */
if (!isset($ARG)) return "⚠️ <b>Não foi possível realizar a consulta.</b>\n\nNo momento, o serviço de consulta de placas está indisponível.\nTente novamente em breve ou <a href=\"https://t.me/GonzalesCanal\">acesse nosso canal oficial</a> para atualizações.";

$placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', $ARG));
if (strlen($placa) < 7 || strlen($placa) > 8) return "⚠️ <b>Placa inválida!</b>";

if ($msg = checkConsultaRepetida('placa', $placa)) return $msg;

/* ===========================================================
   FUNÇÃO PARA LIMPAR CPF/CNPJ (REMOVE ZEROS À ESQUERDA)
   =========================================================== */
function limparCPFCNPJ(string $valor): string {
    $valor = preg_replace('/\D/', '', $valor);
    
    // Remove zeros à esquerda
    $valor = ltrim($valor, '0');
    
    // Se ficou vazio, retorna vazio
    if ($valor === '') return '';
    
    // CPF: garante 11 dígitos
    if (strlen($valor) <= 11) {
        return str_pad($valor, 11, '0', STR_PAD_LEFT);
    }
    
    // CNPJ: garante 14 dígitos
    return str_pad($valor, 14, '0', STR_PAD_LEFT);
}

/* ===========================================================
   FORMATAR CPF/CNPJ
   =========================================================== */
function formatarCPFCNPJ(string $valor): string {
    $limpo = limparCPFCNPJ($valor);
    
    if (strlen($limpo) === 11) {
        // CPF: 123.456.789-01
        return substr($limpo, 0, 3) . '.' . 
               substr($limpo, 3, 3) . '.' . 
               substr($limpo, 6, 3) . '-' . 
               substr($limpo, 9, 2);
    }
    
    if (strlen($limpo) === 14) {
        // CNPJ: 12.345.678/0001-90
        return substr($limpo, 0, 2) . '.' . 
               substr($limpo, 2, 3) . '.' . 
               substr($limpo, 5, 3) . '/' . 
               substr($limpo, 8, 4) . '-' . 
               substr($limpo, 12, 2);
    }
    
    return $valor;
}

/* ===========================================================
   CONSULTA API CHACAL MODS (PRIMÁRIA)
   =========================================================== */
$endpoint = "http://149.56.18.68:25605/api/consulta/placa?dados=" . urlencode($placa) . "&apikey=MalvadezaMods2025";
$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($res, true);
$apiOnline = false;

// Verifica se a API Chacal está online e com dados
if (is_array($data) && 
    isset($data['status']) && 
    $data['status'] === 'online' && 
    !empty($data['resposta'])) {
    
    $apiOnline = true;
    
    // Parse do texto da resposta
    $resposta = $data['resposta'];
    $linhas = explode("\n", $resposta);
    
    $dadosBase1 = [];
    $dadosBase3 = [];
    $dadosBase5 = [];
    $dadosBase6 = [];
    $dadosBase7 = [];
    $baseAtual = null;
    
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        
        if ($linha === 'BASE 1') {
            $baseAtual = 'base1';
            continue;
        } elseif ($linha === 'BASE 3') {
            $baseAtual = 'base3';
            continue;
        } elseif ($linha === 'BASE 5') {
            $baseAtual = 'base5';
            continue;
        } elseif ($linha === 'BASE 6') {
            $baseAtual = 'base6';
            continue;
        } elseif ($linha === 'BASE 7') {
            $baseAtual = 'base7';
            continue;
        }
        
        if (strpos($linha, '⎯') !== false) {
            $partes = explode('⎯', $linha, 2);
            if (count($partes) === 2) {
                $chave = trim($partes[0]);
                $valor = trim($partes[1]);
                
                if ($baseAtual === 'base1') {
                    $dadosBase1[$chave] = $valor;
                } elseif ($baseAtual === 'base3') {
                    $dadosBase3[$chave] = $valor;
                } elseif ($baseAtual === 'base5') {
                    $dadosBase5[$chave] = $valor;
                } elseif ($baseAtual === 'base6') {
                    $dadosBase6[$chave] = $valor;
                } elseif ($baseAtual === 'base7') {
                    $dadosBase7[$chave] = $valor;
                }
            }
        }
    }
    
    // ✅ UNIFICA TODOS OS DADOS EM UM ÚNICO ARRAY
    $dadosUnificados = [];
    
    // Prioridade de dados (evita duplicação)
    // Placa
    $dadosUnificados['placa'] = $dadosBase1['PLACA'] ?? $dadosBase3['PLACA'] ?? $dadosBase5['PLACA'] ?? $dadosBase6['PLACA'] ?? $dadosBase7['PLACA'] ?? '';
    
    // CPF/CNPJ (pode estar como DOCUMENTO, PROPRIETARIO ou CPF_CNPJ)
    if (!empty($dadosBase1['CPF_CNPJ'])) {
        $dadosUnificados['cpf_cnpj'] = formatarCPFCNPJ($dadosBase1['CPF_CNPJ']);
    } elseif (!empty($dadosBase7['CPF'])) {
        $dadosUnificados['cpf_cnpj'] = formatarCPFCNPJ($dadosBase7['CPF']);
    } elseif (!empty($dadosBase3['DOCUMENTO'])) {
        $dadosUnificados['cpf_cnpj'] = formatarCPFCNPJ($dadosBase3['DOCUMENTO']);
    } elseif (!empty($dadosBase6['PROPRIETARIO']) && is_numeric(preg_replace('/\D/', '', $dadosBase6['PROPRIETARIO']))) {
        $dadosUnificados['cpf_cnpj'] = formatarCPFCNPJ($dadosBase6['PROPRIETARIO']);
    } elseif (!empty($dadosBase5['PROPRIETARIO']) && is_numeric(preg_replace('/\D/', '', $dadosBase5['PROPRIETARIO']))) {
        $dadosUnificados['cpf_cnpj'] = formatarCPFCNPJ($dadosBase5['PROPRIETARIO']);
    }
    
    // Proprietário (nome)
    $dadosUnificados['proprietario'] = $dadosBase1['PROPRIETÁRIO'] ?? $dadosBase7['PROPRIETARIO'] ?? '';
    
    // Endereço
    $dadosUnificados['logradouro'] = $dadosBase1['LOGRADOURO'] ?? $dadosBase7['ENDERECO'] ?? '';
    $dadosUnificados['numero'] = $dadosBase1['NÚMERO'] ?? $dadosBase7['NUMERO'] ?? '';
    $dadosUnificados['complemento'] = $dadosBase1['COMPLEMENTO'] ?? $dadosBase7['COMPLEMENTO'] ?? '';
    $dadosUnificados['bairro'] = $dadosBase1['BAIRRO'] ?? $dadosBase7['BAIRRO'] ?? '';
    $dadosUnificados['cidade'] = $dadosBase1['CIDADE'] ?? $dadosBase7['CIDADE'] ?? '';
    $dadosUnificados['estado'] = $dadosBase1['ESTADO'] ?? $dadosBase7['ESTADO'] ?? '';
    $dadosUnificados['cep'] = $dadosBase1['CEP'] ?? $dadosBase7['CEP'] ?? '';
    
    // Veículo - Marca/Modelo
    $dadosUnificados['marca_modelo'] = $dadosBase1['MODELO'] ?? $dadosBase3['MARCA_MODELO'] ?? $dadosBase5['MARCA_MODELO'] ?? $dadosBase6['MARCA_MODELO'] ?? $dadosBase7['MARCA'] ?? '';
    
    // RENAVAM
    $dadosUnificados['renavam'] = $dadosBase1['RENAVAN'] ?? $dadosBase7['RENAVAM'] ?? '';
    
    // Chassi
    $dadosUnificados['chassi'] = $dadosBase1['CHASSI'] ?? $dadosBase3['CHASSI'] ?? $dadosBase5['CHASSI'] ?? $dadosBase6['CHASSI'] ?? $dadosBase7['CHASSI'] ?? '';
    
    // Ano Fabricação
    $dadosUnificados['ano_fabricacao'] = $dadosBase1['ANO_DE_FABRICAÇÃO'] ?? $dadosBase3['ANO_FABRICACAO'] ?? $dadosBase5['ANO_FABRICACAO'] ?? $dadosBase6['ANO_FABRICACAO'] ?? $dadosBase7['ANOFAB'] ?? '';
    
    // Ano Modelo
    $dadosUnificados['ano_modelo'] = $dadosBase3['ANO_MODELO'] ?? $dadosBase5['ANO_MODELO'] ?? $dadosBase6['ANO_MODELO'] ?? $dadosBase7['ANOMODE'] ?? '';
    
    // Detalhes do veículo
    $dadosUnificados['numero_motor'] = $dadosBase3['NUMERO_MOTOR'] ?? $dadosBase5['NUMERO_MOTOR'] ?? $dadosBase6['NUMERO_MOTOR'] ?? '';
    $dadosUnificados['fabricante'] = $dadosBase3['FABRICANTE'] ?? $dadosBase5['FABRICANTE'] ?? $dadosBase6['FABRICANTE'] ?? '';
    $dadosUnificados['combustivel'] = $dadosBase3['COMBUSTIVEL'] ?? $dadosBase5['COMBUSTIVEL'] ?? $dadosBase6['COMBUSTIVEL'] ?? $dadosBase7['COMBUSTIVEL'] ?? '';
    $dadosUnificados['tipo_veiculo'] = $dadosBase3['TIPO_VEICULO'] ?? $dadosBase5['TIPO_VEICULO'] ?? $dadosBase6['TIPO_VEICULO'] ?? '';
    $dadosUnificados['cor'] = $dadosBase3['COR'] ?? $dadosBase5['COR'] ?? $dadosBase6['COR'] ?? '';
    
    // Emplacamento
    $dadosUnificados['uf_placa'] = $dadosBase3['UF_PLACA'] ?? '';
    $dadosUnificados['uf_proprietario'] = $dadosBase5['UF_PROPRIETARIO'] ?? $dadosBase6['UF_PROPRIETARIO'] ?? '';
    $dadosUnificados['uf_jurisdicao'] = $dadosBase5['UF_JURISDICAO'] ?? $dadosBase6['UF_JURISDICAO'] ?? '';
    $dadosUnificados['municipio_emplacamento'] = $dadosBase3['MUNICIPIO_EMPLACAMENTO'] ?? $dadosBase5['MUNICIPIO_EMPLACAMENTO'] ?? $dadosBase6['MUNICIPIO_EMPLACAMENTO'] ?? '';
    $dadosUnificados['data_emplacamento'] = $dadosBase3['DATA_EMPLACAMENTO'] ?? '';
    
    // Datas - Base 7
    if (isset($dadosBase7['DAINCL']) && strlen($dadosBase7['DAINCL']) === 8) {
        $dadosUnificados['data_inclusao'] = substr($dadosBase7['DAINCL'], 6, 2) . '/' . 
                                            substr($dadosBase7['DAINCL'], 4, 2) . '/' . 
                                            substr($dadosBase7['DAINCL'], 0, 4);
    }
    
    if (isset($dadosBase7['DALICE']) && strlen($dadosBase7['DALICE']) === 8) {
        $dadosUnificados['data_licenciamento'] = substr($dadosBase7['DALICE'], 6, 2) . '/' . 
                                                 substr($dadosBase7['DALICE'], 4, 2) . '/' . 
                                                 substr($dadosBase7['DALICE'], 0, 4);
    }
    
    if (isset($dadosBase7['DAMOVI']) && strlen($dadosBase7['DAMOVI']) === 8) {
        $dadosUnificados['data_movimentacao'] = substr($dadosBase7['DAMOVI'], 6, 2) . '/' . 
                                                substr($dadosBase7['DAMOVI'], 4, 2) . '/' . 
                                                substr($dadosBase7['DAMOVI'], 0, 4);
    }
    
    // ✅ MONTA RESPOSTA UNIFICADA SEM DUPLICAÇÃO
    $txt  = "🕵️ <b>CONSULTA DE PLACA COMPLETA</b> 🕵️\n\n";
    $txt .= "🚗 <b>DADOS DO VEÍCULO</b>\n\n";
    
    $txt .= "• <b>Placa:</b> <code>" . ($dadosUnificados['placa'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    $txt .= "• <b>Chassi:</b> <code>" . ($dadosUnificados['chassi'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    $txt .= "• <b>RENAVAM:</b> <code>" . ($dadosUnificados['renavam'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    $txt .= "• <b>Marca/Modelo:</b> <code>" . ($dadosUnificados['marca_modelo'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    
    if (!empty($dadosUnificados['fabricante'])) {
        $txt .= "• <b>Fabricante:</b> <code>{$dadosUnificados['fabricante']}</code>\n";
    }
    
    if (!empty($dadosUnificados['numero_motor'])) {
        $txt .= "• <b>Número Motor:</b> <code>{$dadosUnificados['numero_motor']}</code>\n";
    }
    
    $txt .= "• <b>Combustível:</b> <code>" . ($dadosUnificados['combustivel'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    
    if (!empty($dadosUnificados['tipo_veiculo'])) {
        $txt .= "• <b>Tipo Veículo:</b> <code>{$dadosUnificados['tipo_veiculo']}</code>\n";
    }
    
    if (!empty($dadosUnificados['cor'])) {
        $txt .= "• <b>Cor:</b> <code>{$dadosUnificados['cor']}</code>\n";
    }
    
    $txt .= "• <b>Ano Fabricação:</b> <code>" . ($dadosUnificados['ano_fabricacao'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    
    if (!empty($dadosUnificados['ano_modelo'])) {
        $txt .= "• <b>Ano Modelo:</b> <code>{$dadosUnificados['ano_modelo']}</code>\n";
    }
    $txt .= "\n👤 <b>DADOS DO PROPRIETÁRIO</b>\n\n";
    
    if (!empty($dadosUnificados['cpf_cnpj'])) {
        $txt .= "• <b>CPF/CNPJ:</b> <code>{$dadosUnificados['cpf_cnpj']}</code>\n";
    }
    
    $txt .= "• <b>Proprietário:</b> <code>" . ($dadosUnificados['proprietario'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    $txt .= "• <b>Logradouro:</b> <code>" . ($dadosUnificados['logradouro'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    
    if (!empty($dadosUnificados['numero'])) {
        $txt .= "• <b>Número:</b> <code>{$dadosUnificados['numero']}</code>\n";
    }
    
    if (!empty($dadosUnificados['complemento'])) {
        $txt .= "• <b>Complemento:</b> <code>{$dadosUnificados['complemento']}</code>\n";
    }
    
    $txt .= "• <b>Bairro:</b> <code>" . ($dadosUnificados['bairro'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    $txt .= "• <b>Cidade:</b> <code>" . ($dadosUnificados['cidade'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    $txt .= "• <b>Estado:</b> <code>" . ($dadosUnificados['estado'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    $txt .= "• <b>CEP:</b> <code>" . ($dadosUnificados['cep'] ?: 'SEM INFORMAÇÃO') . "</code>\n";
    
    if (!empty($dadosUnificados['uf_proprietario']) || !empty($dadosUnificados['municipio_emplacamento']) || !empty($dadosUnificados['uf_placa'])) {
        $txt .= "\n📍 <b>EMPLACAMENTO</b>\n\n";
        
        if (!empty($dadosUnificados['municipio_emplacamento'])) {
            $txt .= "• <b>Município:</b> <code>{$dadosUnificados['municipio_emplacamento']}</code>\n";
        }
        
        if (!empty($dadosUnificados['uf_placa'])) {
            $txt .= "• <b>UF Placa:</b> <code>{$dadosUnificados['uf_placa']}</code>\n";
        }
        
        if (!empty($dadosUnificados['uf_proprietario'])) {
            $txt .= "• <b>UF Proprietário:</b> <code>{$dadosUnificados['uf_proprietario']}</code>\n";
        }
        
        if (!empty($dadosUnificados['uf_jurisdicao'])) {
            $txt .= "• <b>UF Jurisdição:</b> <code>{$dadosUnificados['uf_jurisdicao']}</code>\n";
        }
        
        if (!empty($dadosUnificados['data_emplacamento'])) {
            $txt .= "• <b>Data Emplacamento:</b> <code>{$dadosUnificados['data_emplacamento']}</code>\n";
        }
    }
    
    if (!empty($dadosUnificados['data_inclusao']) || !empty($dadosUnificados['data_licenciamento']) || !empty($dadosUnificados['data_movimentacao'])) {
        $txt .= "\n📅 <b>DATAS</b>\n\n";
        
        if (!empty($dadosUnificados['data_inclusao'])) {
            $txt .= "• <b>Data Inclusão:</b> <code>{$dadosUnificados['data_inclusao']}</code>\n";
        }
        
        if (!empty($dadosUnificados['data_licenciamento'])) {
            $txt .= "• <b>Data Licenciamento:</b> <code>{$dadosUnificados['data_licenciamento']}</code>\n";
        }
        
        if (!empty($dadosUnificados['data_movimentacao'])) {
            $txt .= "• <b>Data Movimentação:</b> <code>{$dadosUnificados['data_movimentacao']}</code>\n";
        }
    }
    
    return $txt;
}

/* ===========================================================
   FALLBACK: API ANTIGA (SE CHACAL ESTIVER OFFLINE)
   =========================================================== */
$endpoint2 = "https://meuvpsbr.shop/apis/serpr00o.php?apikey=gonzales&string=" . urlencode($placa);
$ch2 = curl_init($endpoint2);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$res2 = curl_exec($ch2);
curl_close($ch2);

$data2 = json_decode($res2, true);

/* ================= TRATAMENTO DE ERROS DA API ANTIGA ================= */
if (!is_array($data2) || empty($data2['placa'])) {
    return "⚠️ <b>Placa não encontrada!</b>\n\n"
         . "Não foi localizado nenhum veículo com a placa informada.\n"
         . "<i>Verifique se a placa foi digitada corretamente.</i>";
}

/* ===========================================================
   HELPERS (PADRÃO ÚNICO) - API ANTIGA
   =========================================================== */
$g = function ($k) use ($data2) {
    if (!isset($data2[$k]) || $data2[$k] === '' || $data2[$k] === null || $data2[$k] === 'INEXISTENTE') {
        return "<code>SEM INFORMAÇÃO</code>";
    }
    return "<code>" . htmlspecialchars((string)$data2[$k], ENT_QUOTES, 'UTF-8') . "</code>";
};

$gb = fn($v) => $v === true ? "<code>SIM</code>" : "<code>NÃO</code>";

$dt = function ($v) {
    if (!$v) return "<code>SEM INFORMAÇÃO</code>";
    return "<code>" . date('d/m/Y', strtotime(substr($v, 0, 10))) . "</code>";
};

/* ===========================================================
   CALCULA VENCIMENTO DO LICENCIAMENTO (POR FINAL DA PLACA)
   =========================================================== */
function calcularVencimentoLicenciamento(string $placa, ?string $ano): string {
    if (!$placa || !$ano) {
        return "<code>SEM INFORMAÇÃO</code>";
    }

    if (!preg_match('/(\d)$/', $placa, $m)) {
        return "<code>SEM INFORMAÇÃO</code>";
    }

    $final = (int)$m[1];

    $map = [
        1 => 'JANEIRO',
        2 => 'FEVEREIRO',
        3 => 'MARÇO',
        4 => 'ABRIL',
        5 => 'MAIO',
        6 => 'JUNHO',
        7 => 'JULHO',
        8 => 'AGOSTO',
        9 => 'SETEMBRO',
        0 => 'OUTUBRO',
    ];

    $mes = $map[$final] ?? 'SEM INFORMAÇÃO';

    return "<code>{$mes}/{$ano}</code>";
}

/* ===========================================================
   RESPOSTA API ANTIGA
   =========================================================== */
$txt  = "🕵️ <b>CONSULTA DE PLACA COMPLETA</b> 🕵️\n\n";

/* ================= DADOS PRINCIPAIS ================= */
$txt .= "• <b>DADOS PRINCIPAIS</b>\n\n";
$txt .= "• <b>Placa:</b> {$g('placa')}\n";
$txt .= "• <b>Chassi:</b> {$g('chassi')}\n";
$txt .= "• <b>RENAVAM:</b> {$g('codigoRenavam')}\n";
$txt .= "• <b>Situação:</b> {$g('situacao')}\n";
$txt .= "• <b>Município Emplacamento:</b> {$g('descricaoMunicipioEmplacamento')}\n";
$txt .= "• <b>UF Emplacamento:</b> {$g('ufJurisdicao')}\n\n";

/* ================= CARACTERÍSTICAS DO VEÍCULO ================= */
$txt .= "• <b>CARACTERÍSTICAS DO VEÍCULO</b>\n\n";
$txt .= "• <b>Tipo Veículo:</b> {$g('descricaoTipoVeiculo')}\n";
$txt .= "• <b>Espécie:</b> {$g('descricaoEspecieVeiculo')}\n";
$txt .= "• <b>Marca / Modelo:</b> {$g('descricaoMarcaModelo')}\n";
$txt .= "• <b>Tipo Carroceria:</b> {$g('descricaoTipoCarroceria')}\n";
$txt .= "• <b>Cor:</b> {$g('descricaoCor')}\n";
$txt .= "• <b>Categoria:</b> {$g('descricaoCategoria')}\n";
$txt .= "• <b>Ano Fabricação:</b> {$g('anoFabricacao')}\n";
$txt .= "• <b>Ano Modelo:</b> {$g('anoModelo')}\n";
$txt .= "• <b>Potência:</b> {$g('potencia')} cv\n";
$txt .= "• <b>Cilindradas:</b> {$g('cilindradas')}\n";
$txt .= "• <b>Combustível:</b> {$g('descricaoCombustivel')}\n";
$txt .= "• <b>Motor:</b> {$g('numeroMotor')}\n";
$txt .= "• <b>Câmbio:</b> {$g('numeroCambio')}\n";
$txt .= "• <b>Procedência:</b> {$g('procedencia')}\n";
$txt .= "• <b>Qtd. Eixos:</b> {$g('qtdEixos')}\n";
$txt .= "• <b>Lotação:</b> {$g('lotacao')}\n";
$txt .= "• <b>Remarcação Chassi:</b> {$g('descricaoRemarcacaoChassi')}\n\n";

/* ================= PROPRIETÁRIO / FATURAMENTO ================= */
$txt .= "• <b>PROPRIETÁRIO / FATURAMENTO</b>\n\n";
$txt .= "• <b>Tipo Proprietário:</b> {$g('descricaoTipoProprietario')}\n";
$txt .= "• <b>CPF/CNPJ Proprietário:</b> {$g('numeroIdentificacaoProprietario')}\n";
$txt .= "• <b>Nome Proprietário:</b> {$g('nomeProprietario')}\n\n";
$txt .= "• <b>Tipo Documento Faturado:</b> {$g('tipoDocFaturado')}\n";
$txt .= "• <b>Documento Faturado:</b> {$g('numeroIdFaturamento')}\n";
$txt .= "• <b>UF Faturado:</b> {$g('ufFaturado')}\n\n";

/* ================= RESTRIÇÕES / ALERTAS ================= */
$txt .= "• <b>RESTRIÇÕES / ALERTAS</b>\n\n";
$txt .= "• <b>Restrição 1:</b> {$g('descricaoRestricao1')}\n";
$txt .= "• <b>Restrição 2:</b> {$g('descricaoRestricao2')}\n";
$txt .= "• <b>Restrição 3:</b> {$g('descricaoRestricao3')}\n";
$txt .= "• <b>Restrição 4:</b> {$g('descricaoRestricao4')}\n";
$txt .= "• <b>Restrição RFB:</b> {$g('descricaoRestricaoRfb')}\n";
$txt .= "• <b>Multa RENAINF:</b> {$gb($data2['indicadorMultaRenainf'] ?? null)}\n";
$txt .= "• <b>Restrição RENAJUD:</b> {$gb($data2['indicadorRestricaoRenajud'] ?? null)}\n\n";
$txt .= "• <b>Roubo / Furto:</b> {$gb($data2['indicadorRouboFurto'] ?? null)}\n";
$txt .= "• <b>Leilão:</b> {$gb($data2['indicadorLeilao'] ?? null)}\n";
$txt .= "• <b>Comunicação de Venda:</b> {$gb($data2['indicadorComunicacaoVenda'] ?? null)}\n";
$txt .= "• <b>Pendência de Emissão:</b> {$gb($data2['indicadorPendenciaEmissao'] ?? null)}\n";
$txt .= "• <b>Alarme:</b> {$gb($data2['indicadorAlarme'] ?? null)}\n\n";

/* ================= RECALL ================= */
$recalls = [];
for ($i = 1; $i <= 4; $i++) {
    if (!empty($data2["indicadorRecall{$i}"])) {
        $recalls[] = "Recall {$i}";
    }
}
$txt .= "• <b>Recall(s):</b> <code>" . (empty($recalls) ? 'SEM INFORMAÇÃO' : implode(', ', $recalls)) . "</code>\n\n";

/* ================= IMPORTAÇÃO ================= */
$txt .= "• <b>IMPORTAÇÃO</b>\n\n";
$txt .= "• <b>Natureza:</b> {$g('naturezaImportacao')}\n";
$txt .= "• <b>Tipo Importação:</b> {$g('descricaoTipoImportacao')}\n";
$txt .= "• <b>Órgão RFB:</b> {$g('descricaoOrgaoRfb')}\n";
$txt .= "• <b>País Transferência:</b> {$g('descricaoPaisTransferencia')}\n\n";

/* ================= DATAS / SERVIÇOS ================= */
$anoLic = $data2['anoExercicioLicenciamentoPago'] ?? null;
$venc   = calcularVencimentoLicenciamento($placa, $anoLic);

$txt .= "• <b>DATAS E SERVIÇOS</b>\n\n";
$txt .= "• <b>Data Emissão CRV:</b> {$dt($data2['dataEmissaoCrv'] ?? null)}\n";
$txt .= "• <b>Data Emissão CRLV:</b> {$dt($data2['dataEmissaoCRLV'] ?? null)}\n";
$txt .= "• <b>Data Transferência:</b> {$dt($data2['dataTransferencia'] ?? null)}\n";
$txt .= "• <b>Ano Licenciamento:</b> {$g('anoExercicioLicenciamentoPago')}\n";
$txt .= "• <b>Vencimento do Documento:</b> {$venc}\n";
$txt .= "• <b>Serviço Consultado:</b> {$g('servicoConsultado')}\n";

return $txt;
