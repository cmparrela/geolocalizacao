<?php

namespace Procedo;

use Exception;

class Geolocalizacao
{
    private $chave;
    private $tentativa;

    public function __construct()
    {
        // Define como a primeira tentativa
        $this->tentativa = 1;
    }

    /**
     * Define qual é a chave de acesso a API do Google
     * @param type $chave
     */
    public function defineChave($chave)
    {
        $this->chave = $chave;
    }

    /**
     * Realiza consulta no Google Maps.
     *
     * Para os retorno de erro os códigos são:
     * 1 = Chave não definida.
     * 2 = Cidade e UF são obrigatório.
     * 3 = Erro ao realizar a requisição.
     * 4 = Atingiu o limite de consultas diario disponível.
     *
     * @param type $dados
     * @return array Segue abaixo dois exemplos de retorno
     *        Retorno pra erro [
     *            sucesso => boolean,
     *            erro => [
     *                mensagem => string,
     *                codigo => int
     *            ]
     *        ]
     *
     *        Retorno para sucesso [
     *            sucesso => boolean,
     *            localizacao => [
     *                lat => string,
     *                lng => string
     *            ]
     *        ]
     * @throws Exception
     */
    public function buscaGeolocalizacao($dados)
    {
        try {
            $this->validaDados($dados);

            $resp = $this->requisicao($dados);

            switch ($resp['status']) {
                case 'OK':
                    $localizacao = $resp['results'][0]['geometry']['location'];
                    return ['sucesso' => true, 'localizacao' => $localizacao];
                case 'OVER_QUERY_LIMIT':
                    throw new Exception('Você já atingiu o limite de consultas diario disponível', 4);
                case 'ZERO_RESULTS':
                    // Se não tiver resultado tenta novamente sem alguns campos
                    if ($this->tentativa == 1) {
                        $this->tentativa = 2;
                        unset($dados['numero']);
                        unset($dados['logradouro']);
                        return $this->buscaGeolocalizacao($dados);
                    }
                    break;
            }
        } catch (Exception $e) {
            $erro = ['codigo' => $e->getCode(), 'mensagem' => $e->getMessage()];
            return ['sucesso' => false, 'erro' => $erro];
        }
    }

    /**
     * Faz uma validação com os dados obrigatórios que são:
     * - A chave (key) do google
     * - Cidade
     * - Estado
     * @param array $dados Array com os dados que serão utilizados na busca.
     * @throws Exception
     */
    public function validaDados($dados)
    {
        // Valida os dados, em caso de erro gera um exception
        if (empty($this->chave)) {
            throw new Exception('Chave não definida', 1);
        }
        if (empty($dados['cidade']) && empty($dados['uf'])) {
            throw new Exception('Cidade e UF são obrigatório', 2);
        }
    }

    /**
     * Executa a requisição
     *
     * @param array $dados Array com os dados que serão utilizados na busca.
     * @return object
     * @throws Exception
     */
    private function requisicao($dados)
    {
        $url = $this->montaURL($dados);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $retorno = curl_exec($curl);

        if ($retorno) {
            return json_decode($retorno, true);
        } else {
            throw new Exception('erro no cUrl:' . curl_error($curl), 3);
        }
    }

    /**
     * Monta a URL para fazer a requisição
     * @param array $dados Array com os dados que serão utilizados na busca.
     * @return string
     */
    private function montaURL($dados)
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=';
        if (!empty($dados['logradouro'])) {
            $url .= urlencode($dados['logradouro'] . ", ");
            if (!empty($dados['numero'])) {
                $url .= urlencode(str_replace('-', '', $dados['numero']) . ", ");
            }
        }
        if (!empty($dados['cidade'])) {
            $url .= urlencode($dados['cidade'] . ", ");
        }
        if (!empty($dados['uf'])) {
            $url .= urlencode($dados['uf']);
        }
        $url .= '&key=' . $this->chave;

        return $url;
    }

}
