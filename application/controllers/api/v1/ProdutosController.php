<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class ProdutosController extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('produtos_model');
        $this->load->helper('validation_helper');
    }

    public function index_get($id = '')
    {
        $this->logged_user();
        if (! $this->permission->checkPermission($this->logged_user()->level, 'vProduto')) {
            $this->response([
                'status' => false,
                'message' => 'Você não está autorizado a Visualizar Produtos',
            ], REST_Controller::HTTP_UNAUTHORIZED);
        }

        if (! $id) {
            $search = trim((string) $this->get('search', true));
            $perPage = (int) ($this->get('perPage', true) ?: 20) ?: 20;
            $page = (int) ($this->get('page', true) ?: 0);
            $start = $page > 0 ? ($perPage * $page) : 0;

            if ($search !== '') {
                $words = array_filter(preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY));
                $words = array_values($words);
                if (! empty($words)) {
                    $produtos = $this->produtos_model->searchProducts($words, $perPage, $start);
                } else {
                    $produtos = $this->produtos_model->get('produtos', '*', '', $perPage, $start);
                }
            } else {
                $produtos = $this->produtos_model->get('produtos', '*', '', $perPage, $start);
            }

            $this->response([
                'status' => true,
                'message' => 'Listando Produtos',
                'result' => $produtos,
            ], REST_Controller::HTTP_OK);
        }

        if ($id && is_numeric($id)) {
            $produto = $this->produtos_model->getById($id);

            $this->response([
                'status' => true,
                'message' => 'Detalhes do Produto',
                'result' => $produto,
            ], REST_Controller::HTTP_OK);
        }

        $this->response([
            'status' => false,
            'message' => 'Nenhum Produto localizado.',
            'result' => null,
        ], REST_Controller::HTTP_OK);
    }

    public function index_post()
    {
        $this->logged_user();
        if (! $this->permission->checkPermission($this->logged_user()->level, 'aProduto')) {
            $this->response([
                'status' => false,
                'message' => 'Você não está autorizado a Adicionar Produtos!',
            ], REST_Controller::HTTP_UNAUTHORIZED);
        }

        $_POST = (array) json_decode(file_get_contents('php://input'), true);

        $this->load->library('form_validation');

        if ($this->form_validation->run('produtos') == false) {
            $this->response([
                'status' => false,
                'message' => validation_errors(),
            ], REST_Controller::HTTP_BAD_REQUEST);
        }

        $precoCompra = $this->post('precoCompra', true);
        $precoCompra = str_replace(',', '', $precoCompra);
        $precoVenda = $this->post('precoVenda', true);
        $precoVenda = str_replace(',', '', $precoVenda);
        $data = [
            'codDeBarra' => $this->post('codDeBarra', true),
            'descricao' => $this->post('descricao', true),
            'unidade' => $this->post('unidade', true),
            'precoCompra' => $precoCompra,
            'precoVenda' => $precoVenda,
            'estoque' => $this->post('estoque', true),
            'estoqueMinimo' => $this->post('estoqueMinimo', true) ? $this->post('estoqueMinimo', true) : 0,
            'saida' => $this->post('saida', true) ? $this->post('saida', true) : 0,
            'entrada' => $this->post('entrada', true) ? $this->post('entrada', true) : 0,
        ];

        if ($this->produtos_model->add('produtos', $data)) {
            $this->response([
                'status' => true,
                'message' => 'Produto adicionado com sucesso!',
                'result' => $this->produtos_model->get('produtos', '*', "descricao = '{$data['descricao']}'", 1, 0, true),
            ], REST_Controller::HTTP_CREATED);
        }

        $this->response([
            'status' => false,
            'message' => 'Não foi possível adicionar o Produto. Avise ao Administrador.',
        ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function index_put($id)
    {
        $this->logged_user();

        if (! $this->permission->checkPermission($this->logged_user()->level, 'eProduto')) {
            $this->response([
                'status' => false,
                'message' => 'Você não está autorizado a Editar Produtos!',
            ], REST_Controller::HTTP_UNAUTHORIZED);
        }

        if (! $id) {
            $this->response([
                'status' => false,
                'message' => 'Informe o ID do produto!',
            ], REST_Controller::HTTP_BAD_REQUEST);
        }

        if (! $this->put('descricao', true) ||
        ! $this->put('unidade', true) ||
        ! $this->put('precoCompra', true) ||
        ! $this->put('precoVenda', true) ||
        ! $this->put('estoque', true)) {
            $this->response([
                'status' => false,
                'message' => 'Preencha todos os campos obrigatórios!',
            ], REST_Controller::HTTP_BAD_REQUEST);
        }

        $precoCompra = $this->put('precoCompra', true);
        $precoCompra = str_replace(',', '', $precoCompra);
        $precoVenda = $this->put('precoVenda', true);
        $precoVenda = str_replace(',', '', $precoVenda);
        $data = [
            'codDeBarra' => $this->put('codDeBarra', true) ?: 0,
            'descricao' => $this->put('descricao', true),
            'unidade' => $this->put('unidade', true),
            'precoCompra' => $precoCompra,
            'precoVenda' => $precoVenda,
            'estoque' => $this->put('estoque', true),
            'estoqueMinimo' => $this->put('estoqueMinimo', true) ?: 0,
            'saida' => $this->put('saida', true) ?: 0,
            'entrada' => $this->put('entrada', true) ?: 0
        ];

        if ($this->produtos_model->edit('produtos', $data, 'idProdutos', $id)) {
            $this->response([
                'status' => true,
                'message' => 'Produto editado com sucesso!',
                'result' => $this->produtos_model->getById($id),
            ], REST_Controller::HTTP_OK);
        }

        $this->response([
            'status' => false,
            'message' => 'Não foi possível editar o Produto. Avise ao Administrador.',
        ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * BOM-BOT: importa itens de cotação.
     * Cria/atualiza produtos e soma estoque sem sobrescrever a quantidade atual.
     * POST /api/v1/produtos/importar-cotacao
     */
    public function importar_cotacao_post()
    {
        $this->logged_user();
        if (! $this->permission->checkPermission($this->logged_user()->level, 'aProduto')) {
            $this->response(['status' => false, 'message' => 'Sem permissão para importar produtos.'], REST_Controller::HTTP_UNAUTHORIZED);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $itens = isset($input['itens']) && is_array($input['itens']) ? $input['itens'] : [];
        $markup = isset($input['markup_percent']) ? (float) $input['markup_percent'] : 43.0;
        $fornecedor = (string) ($input['fornecedor'] ?? '');
        $referencia = (string) ($input['referencia'] ?? '');
        $resultados = [];

        foreach ($itens as $it) {
            $descricao = trim((string) ($it['descricao'] ?? ''));
            $codigo = trim((string) ($it['codigo'] ?? ''));
            $ean = trim((string) ($it['ean'] ?? ''));
            $unidade = trim((string) ($it['unidade'] ?? 'UN')) ?: 'UN';
            $qtd = (float) ($it['quantidade'] ?? 0);
            $compra = (float) ($it['preco_compra'] ?? 0);
            $venda = (float) ($it['preco_venda'] ?? 0);
            $acao = (string) ($it['acao'] ?? 'novo');
            $somar = ! empty($it['adicionar_estoque']);
            $produtoId = (int) ($it['produto_id'] ?? 0);

            if ($descricao === '' || $qtd <= 0 || $compra < 0) {
                $resultados[] = ['ok' => false, 'cotacao_item_id' => $it['cotacao_item_id'] ?? null,
                    'descricao' => $descricao, 'erro' => 'Descrição/quantidade/preço inválidos'];
                continue;
            }
            if ($venda <= 0) {
                $venda = round($compra * (1 + $markup / 100), 2);
            }

            // Localiza por id, código de barras ou EAN antes de criar
            $existente = null;
            if ($produtoId > 0) {
                $existente = $this->produtos_model->getById($produtoId);
            }
            if (! $existente && ($codigo !== '' || $ean !== '')) {
                $chave = $codigo !== '' ? $codigo : $ean;
                $existente = $this->db->get_where('produtos', ['codDeBarra' => $chave])->row();
            }

            if ($existente) {
                $this->db->where('idProdutos', $existente->idProdutos);
                $this->db->update('produtos', [
                    'precoCompra' => $compra,
                    'precoVenda' => $venda,
                ]);
                if ($somar) {
                    $this->db->query('UPDATE produtos SET estoque = estoque + ? WHERE idProdutos = ?', [$qtd, $existente->idProdutos]);
                }
                $novo = $this->produtos_model->getById($existente->idProdutos);
                $resultados[] = ['ok' => true, 'cotacao_item_id' => $it['cotacao_item_id'] ?? null,
                    'produto_id' => (int) $existente->idProdutos, 'acao' => 'atualizado',
                    'descricao' => $existente->descricao, 'estoque' => (float) $novo->estoque];
                continue;
            }

            if ($acao === 'ambiguo') {
                $resultados[] = ['ok' => false, 'cotacao_item_id' => $it['cotacao_item_id'] ?? null,
                    'descricao' => $descricao, 'erro' => 'Item ambíguo: confirme o produto'];
                continue;
            }

            $cod = $codigo !== '' ? mb_substr($codigo, 0, 70) : ('COT-' . date('YmdHis') . '-' . count($resultados));
            $dados = [
                'codDeBarra' => $cod,
                'descricao' => mb_substr($descricao, 0, 80),
                'unidade' => mb_substr($unidade, 0, 10),
                'precoCompra' => $compra,
                'precoVenda' => $venda,
                'estoque' => $somar ? $qtd : 0,
                'estoqueMinimo' => 0,
                'saida' => 1,
                'entrada' => 1,
            ];
            $this->db->insert('produtos', $dados);
            $novoId = (int) $this->db->insert_id();
            if (! $novoId) {
                $resultados[] = ['ok' => false, 'cotacao_item_id' => $it['cotacao_item_id'] ?? null,
                    'descricao' => $descricao, 'erro' => 'Falha ao cadastrar produto'];
                continue;
            }
            $resultados[] = ['ok' => true, 'cotacao_item_id' => $it['cotacao_item_id'] ?? null,
                'produto_id' => (int) $novoId, 'acao' => 'criado', 'descricao' => $descricao, 'estoque' => $somar ? $qtd : 0];
        }

        $this->log_app('BOM-BOT: importou cotação de ' . $fornecedor . ' (' . $referencia . ') com ' . count($resultados) . ' item(ns).');
        $this->response(['status' => true, 'message' => 'Importação concluída.', 'result' => $resultados], REST_Controller::HTTP_CREATED);
    }

    public function index_delete($id)
    {
        $this->logged_user();
        if (! $this->permission->checkPermission($this->logged_user()->level, 'dProduto')) {
            $this->response([
                'status' => false,
                'message' => 'Você não está autorizado a Apagar Produtos!',
            ], REST_Controller::HTTP_UNAUTHORIZED);
        }

        if (! $id) {
            $this->response([
                'status' => false,
                'message' => 'Informe o ID do Produto!',
            ], REST_Controller::HTTP_BAD_REQUEST);
        }

        $this->produtos_model->delete('produtos_os', 'produtos_id', $id);
        $this->produtos_model->delete('itens_de_vendas', 'produtos_id', $id);

        if ($this->produtos_model->delete('produtos', 'idProdutos', $id)) {
            $this->log_app('Removeu um Produto. ID' . $id);
            $this->response([
                'status' => true,
                'message' => 'Produto excluído com sucesso!',
            ], REST_Controller::HTTP_OK);
        }

        $this->response([
            'status' => false,
            'message' => 'Não foi possível excluir o Produto. Avise ao Administrador.',
        ], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
    }
}
