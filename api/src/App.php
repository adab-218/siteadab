<?php

declare(strict_types=1);

namespace Adab\Api;

use Adab\Api\Controllers\AcoesController;
use Adab\Api\Controllers\AdminController;
use Adab\Api\Controllers\AuthController;
use Adab\Api\Controllers\CatalogosController;
use Adab\Api\Controllers\CultosController;
use Adab\Api\Controllers\DepartamentosController;
use Adab\Api\Controllers\EbdController;
use Adab\Api\Controllers\EventosController;
use Adab\Api\Controllers\MembrosController;
use Adab\Api\Controllers\PublicController;
use Adab\Api\Controllers\UsuariosController;
use Adab\Api\Middleware\AuthMiddleware;
use Adab\Api\Middleware\CsrfMiddleware;
use Adab\Api\Middleware\PerfilMiddleware;
use PDO;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

/**
 * Monta a aplicação Slim com todas as rotas e middlewares.
 *
 * Extraída do index.php para permitir testes de integração HTTP
 * (T2.5) — o bootstrap (config, sessão, banco) fica no index.php.
 */
function criarApp(PDO $pdo, array $config): App
{
    $app = AppFactory::create();
    $app->setBasePath('/api');
    $app->addBodyParsingMiddleware();

    $app->get('/health', fn($req, $res) => Json::resposta($res, ['data' => ['ok' => true]]));

    // Auth
    $app->group('/auth', function (RouteCollectorProxy $g) use ($pdo, $config) {
        $auth = new AuthController($pdo, $config);
        $g->post('/login', [$auth, 'login']);
        $g->post('/logout', [$auth, 'logout']);
        $g->get('/me', [$auth, 'me'])->add(new AuthMiddleware($pdo));
        $g->post('/recuperar', [$auth, 'recuperar']);
        $g->post('/redefinir', [$auth, 'redefinir']);
    });

    // Site público — sem autenticação, dados públicos apenas
    $app->group('/public', function (RouteCollectorProxy $g) use ($pdo) {
        $pub = new PublicController($pdo);
        $g->get('/programacao', [$pub, 'programacao']);
        $g->get('/eventos', [$pub, 'eventos']);
    });

    // Admin — POST /migrate: sessão admin OU X-Migrate-Token (deploy)
    $app->group('/admin', function (RouteCollectorProxy $g) use ($pdo, $config) {
        $admin = new AdminController($pdo, $config);
        $g->post('/migrate', [$admin, 'migrate']);
    });

    // Rotas autenticadas: TODA rota de módulo deve ser registrada neste
    // grupo (CSRF + Auth aplicados na ordem correta pelo encadeamento).
    // GETs são abertos a todo perfil autenticado (lider_departamento tem
    // escopo aplicado dentro do controller — regra D5); escritas exigem
    // PerfilMiddleware explícito.
    $app->group('', function (RouteCollectorProxy $g) use ($pdo) {
        $g->get('/cargos', [new CatalogosController($pdo), 'cargos']);

        $membros = new MembrosController($pdo);
        $g->group('/membros', function (RouteCollectorProxy $m) use ($membros) {
            $m->get('', [$membros, 'listar']);
            $m->get('/aniversariantes', [$membros, 'aniversariantes']);
            $m->get('/{id}', [$membros, 'ver']);
            $m->post('', [$membros, 'criar'])
              ->add(PerfilMiddleware::para(['secretaria']));
            $m->put('/{id}', [$membros, 'atualizar'])
              ->add(PerfilMiddleware::para(['secretaria']));
            $m->patch('/{id}/status', [$membros, 'status'])
              ->add(PerfilMiddleware::para(['secretaria']));
            $m->delete('/{id}', [$membros, 'remover'])
              ->add(PerfilMiddleware::para([])); // lista vazia = só admin
        });

        $deptos = new DepartamentosController($pdo);
        $g->group('/departamentos', function (RouteCollectorProxy $d) use ($deptos) {
            $d->get('', [$deptos, 'listar']);
            $d->get('/{id}', [$deptos, 'ver']);
            $d->post('', [$deptos, 'criar'])
              ->add(PerfilMiddleware::para(['secretaria']));
            $d->put('/{id}', [$deptos, 'atualizar'])
              ->add(PerfilMiddleware::para(['secretaria']));
            $d->delete('/{id}', [$deptos, 'remover'])
              ->add(PerfilMiddleware::para(['secretaria']));
            // Vínculo de membros: secretaria/admin ou o próprio líder do
            // departamento (D5 — o controller valida a liderança por registro).
            $d->post('/{id}/membros', [$deptos, 'adicionarMembro'])
              ->add(PerfilMiddleware::para(['secretaria', 'lider_departamento']));
            $d->delete('/{id}/membros/{pessoa_id}', [$deptos, 'removerMembro'])
              ->add(PerfilMiddleware::para(['secretaria', 'lider_departamento']));
        });

        // M3 Cultos: agenda + tipos. Escritas de culto: secretaria/dirigente/
        // admin; catálogo de tipos: secretaria/admin.
        $cultos = new CultosController($pdo);
        $g->group('/tipos-culto', function (RouteCollectorProxy $t) use ($cultos) {
            $t->get('', [$cultos, 'listarTipos']);
            $t->post('', [$cultos, 'criarTipo'])
              ->add(PerfilMiddleware::para(['secretaria']));
            $t->put('/{id}', [$cultos, 'atualizarTipo'])
              ->add(PerfilMiddleware::para(['secretaria']));
            $t->delete('/{id}', [$cultos, 'desativarTipo'])
              ->add(PerfilMiddleware::para(['secretaria']));
        });
        $g->group('/cultos', function (RouteCollectorProxy $c) use ($cultos) {
            $c->get('', [$cultos, 'listar']);
            $c->get('/{id}', [$cultos, 'ver']);
            $c->post('', [$cultos, 'criar'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $c->put('/{id}', [$cultos, 'atualizar'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $c->delete('/{id}', [$cultos, 'remover'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            // Presença nominal (uq_culto_pessoa → add idempotente)
            $c->get('/{id}/presencas', [$cultos, 'listarPresencas']);
            $c->post('/{id}/presencas', [$cultos, 'adicionarPresenca'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $c->delete('/{id}/presencas/{pessoa_id}', [$cultos, 'removerPresenca'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
        });

        // M4 Eventos: CRUD + programação interna (atividades).
        // Escrita: secretaria/dirigente/admin; leitura: qualquer autenticado.
        $eventos = new EventosController($pdo);
        $g->group('/eventos', function (RouteCollectorProxy $e) use ($eventos) {
            $e->get('', [$eventos, 'listar']);
            $e->get('/{id}', [$eventos, 'ver']);
            $e->post('', [$eventos, 'criar'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $e->put('/{id}', [$eventos, 'atualizar'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $e->delete('/{id}', [$eventos, 'remover'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $e->post('/{id}/atividades', [$eventos, 'criarAtividade'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $e->put('/{id}/atividades/{aid}', [$eventos, 'atualizarAtividade'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $e->delete('/{id}/atividades/{aid}', [$eventos, 'removerAtividade'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
        });

        // M5 Ações: CRUD + voluntários + beneficiários.
        $acoes = new AcoesController($pdo);
        $g->group('/acoes', function (RouteCollectorProxy $a) use ($acoes) {
            $a->get('', [$acoes, 'listar']);
            $a->get('/{id}', [$acoes, 'ver']);
            $a->post('', [$acoes, 'criar'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $a->put('/{id}', [$acoes, 'atualizar'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $a->delete('/{id}', [$acoes, 'remover'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $a->post('/{id}/voluntarios', [$acoes, 'vincularVoluntario'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $a->delete('/{id}/voluntarios/{pessoa_id}', [$acoes, 'desvincularVoluntario'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $a->post('/{id}/beneficiarios', [$acoes, 'criarBeneficiario'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
            $a->delete('/{id}/beneficiarios/{bid}', [$acoes, 'removerBeneficiario'])
              ->add(PerfilMiddleware::para(['secretaria', 'dirigente']));
        });

        // M6 EBD: classes, professores, matrículas (D2), períodos/aulas
        // (D4/D8), chamada (D3 — professor da classe ou ebd_admin) e
        // relatórios. Escritas de catálogo: ebd_admin (+admin automático).
        $ebd = new EbdController($pdo);
        $g->group('/ebd', function (RouteCollectorProxy $e) use ($ebd) {
            $e->get('/classes', [$ebd, 'listarClasses']);
            $e->post('/classes', [$ebd, 'criarClasse'])
              ->add(PerfilMiddleware::para(['ebd_admin']));
            $e->put('/classes/{id}', [$ebd, 'atualizarClasse'])
              ->add(PerfilMiddleware::para(['ebd_admin']));
            $e->get('/classes/{id}/professores', [$ebd, 'professoresDaClasse']);
            $e->post('/classes/{id}/professores', [$ebd, 'vincularProfessor'])
              ->add(PerfilMiddleware::para(['ebd_admin']));
            $e->delete('/classes/{id}/professores/{pid}', [$ebd, 'desvincularProfessor'])
              ->add(PerfilMiddleware::para(['ebd_admin']));

            $e->get('/alunos', [$ebd, 'listarAlunos'])
              ->add(PerfilMiddleware::para(['ebd_admin', 'ebd_professor']));
            $e->post('/alunos', [$ebd, 'matricular'])
              ->add(PerfilMiddleware::para(['ebd_admin']));
            $e->post('/alunos/{id}/transferir', [$ebd, 'transferir'])
              ->add(PerfilMiddleware::para(['ebd_admin']));
            $e->post('/alunos/{id}/desistir', [$ebd, 'desistir'])
              ->add(PerfilMiddleware::para(['ebd_admin']));

            $e->get('/periodos', [$ebd, 'listarPeriodos']);
            $e->post('/periodos', [$ebd, 'criarPeriodo'])
              ->add(PerfilMiddleware::para(['ebd_admin']));
            $e->get('/periodos/{id}/aulas', [$ebd, 'aulasDoPeriodo']);
            $e->post('/periodos/{id}/aulas', [$ebd, 'criarAula'])
              ->add(PerfilMiddleware::para(['ebd_admin']));
            $e->post('/periodos/{id}/gerar-aulas', [$ebd, 'gerarAulas'])
              ->add(PerfilMiddleware::para(['ebd_admin']));

            $e->get('/aulas/{id}/chamada', [$ebd, 'verChamada'])
              ->add(PerfilMiddleware::para(['ebd_admin', 'ebd_professor']));
            $e->put('/aulas/{id}/chamada', [$ebd, 'salvarChamada'])
              ->add(PerfilMiddleware::para(['ebd_admin', 'ebd_professor']));

            $e->get('/relatorios/frequencia', [$ebd, 'relatorioFrequencia'])
              ->add(PerfilMiddleware::para(['ebd_admin', 'dirigente']));
            $e->get('/relatorios/faltosos', [$ebd, 'relatorioFaltosos'])
              ->add(PerfilMiddleware::para(['ebd_admin']));
        });

        // Gestão de usuários — todo o grupo é só-admin (lista vazia).
        $usuarios = new UsuariosController($pdo);
        $g->group('/usuarios', function (RouteCollectorProxy $u) use ($usuarios) {
            $u->get('', [$usuarios, 'listar']);
            $u->post('', [$usuarios, 'criar']);
            $u->put('/{id}', [$usuarios, 'atualizar']);
            $u->patch('/{id}/senha', [$usuarios, 'senha']);
            $u->delete('/{id}', [$usuarios, 'remover']);
        })->add(PerfilMiddleware::para([]));
    })->add(new CsrfMiddleware())->add(new AuthMiddleware($pdo));

    // -----------------------------------------------------------------
    // Erros — sempre JSON
    // -----------------------------------------------------------------
    $errorMiddleware = $app->addErrorMiddleware(false, true, true);
    $errorMiddleware->setDefaultErrorHandler(function ($request, \Throwable $e) use ($app) {
        $status = 500;
        if ($e instanceof \Slim\Exception\HttpException) {
            $status = $e->getCode() ?: 500;
        }
        error_log('[adab-api] ' . $e->getMessage());

        $codigos = [
            400 => ['REQUISICAO_INVALIDA', 'Requisição inválida.'],
            404 => ['NOT_FOUND', 'Rota não encontrada.'],
            405 => ['METODO_NAO_PERMITIDO', 'Método não permitido.'],
        ];
        [$code, $message] = $codigos[$status] ?? ['ERRO_INTERNO', 'Erro interno do servidor.'];

        $response = $app->getResponseFactory()->createResponse();
        return Json::resposta($response, ['error' => [
            'code' => $code,
            'message' => $message,
        ]], $status);
    });

    return $app;
}
