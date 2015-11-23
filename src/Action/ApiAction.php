<?php
namespace LosApi\Action;

use Nocarrier\Hal;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Db\TableGateway\TableGateway;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Paginator\Adapter\DbTableGateway;
use Zend\Paginator\Paginator;
use Zend\Stratigility\MiddlewareInterface;

final class ApiAction implements MiddlewareInterface
{

    private $table;

    private $request;

    private $response;

    private $actionOptions;

    private $entityOptions;

    private $provider;

    public function __construct(TableGateway $table, ProviderInterface $provider = null, $actionOptions = [], $entityOptions = [])
    {
        $this->actionOptions = array_merge([
            'provider' => null,
            'route_name' => 'v1.agora',
            'table_name' => 'agora',
            'route_identifier_name' => 'id',
            'collection_name' => 'agora',
            'entity_http_methods' => array(
                'GET',
            ),
            'collection_http_methods' => array(
                'GET',
            ),
            'collection_query_whitelist' => array(
                'empresaId',
            ),
            'page_size' => 25,
            'page_size_param' => 'page',
            'entity_class' => 'Dashboard\Entity\Agora',
            'collection_class' => 'Dashboard\Entity\AgoraCollection',
            'service_name' => 'Visitante',
        ], $actionOptions);

        $this->entityOptions = $entityOptions;

        $this->table = $table;

        $this->provider = $provider;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $this->request = $request;
        $this->response = $response;

        $idName = $this->actionOptions['route_identifier_name'];
        $id = $request->getAttribute($idName);

        if ($id === null) {
            $response = $this->handleCollection();
        } else {
            $response = $this->handleEntity($id);
        }

        return $next($request, $response);
    }

    private function handleCollection()
    {
        if (! in_array($this->request->getMethod(), $this->actionOptions['collection_http_methods'])) {
            throw new \Exception(sprintf("Method '%s' not allowed for collectons", $this->request->getMethod()), 405);
        }
        switch ($this->request->getMethod()) {
            case 'GET':
                $result = $this->fetchAll();
                break;
            case 'POST':
                $result = $this->create();
                break;
        }

        return new JsonResponse($result);
    }

    private function handleEntity($id)
    {
        if (! in_array($this->request->getMethod(), $this->actionOptions['entity_http_methods'])) {
            throw new \Exception(sprintf("Method '%s' not allowed for entities", $this->request->getMethod()), 405);
        }
        switch ($this->request->getMethod()) {
            case 'GET':
                $result = $this->fetch($id);
                break;
            case 'POST':
                return $this->fetch();
            case 'PUT':
                return $this->fetch();
            case 'PATCH':
                return $this->fetch();
            case 'DELETE':
                return $this->fetch();
        }

        return new JsonResponse($result);
    }

    private function fetchAll()
    {
        $query = $this->request->getQueryParams();

        $filtro = [
            'empresa_id' => null,
            'rede_id' => null
        ];

        if (array_key_exists('empresa', $query)) {
            $empresaId = $query['empresa'];
            if (is_numeric($empresaId)) {
                $filtro['empresa_id'] = (int) $empresaId;
            }
        }
        if (array_key_exists('rede', $query)) {
            $redeId = $query['rede'];
            if (is_numeric($redeId)) {
                $filtro['rede_id'] = (int) $redeId;
            }
        }

        $page = 1;
        if (array_key_exists('page', $query)) {
            $page = (int) $query['page'];
        }

        $paginator = new Paginator(new DbTableGateway($this->table, $filtro));
        $paginator->setCurrentPageNumber($page);
        $paginator->setItemCountPerPage(2);
        $items = $paginator->getCurrentItems();

        if (0 === count($items)) {
            throw new \Exception("Dashboard não encontrado.", 404);
        }

        $selfLink = http_build_query(array_merge($query));
        $hal = new Hal((string) $this->request->getUri()->withQuery($selfLink), [
            'page_count' => $paginator->count(),
            'page_size' => $paginator->getItemCountPerPage(),
            'total_items' => $paginator->getTotalItemCount(),
            'page' => $page
        ]);

        foreach ($items as $item) {
            $halItem = new Hal((string) $this->request->getUri()->withQuery('') . '/' . $item->getId(), $item->getArrayCopy());
            $hal->addResource('agora', $halItem);
        }

        $numPages = $paginator->count();

        unset($query['page']);
        if ($paginator->count() > 1) {
            $firstLink = http_build_query($query);
            $hal->addLink('first', (string) $this->request->getUri()
                ->withQuery($firstLink));
        }

        if ($page < $numPages) {
            $nextLink = http_build_query(array_merge($query, [
                'page' => $page + 1
            ]));
            $hal->addLink('next', (string) $this->request->getUri()
                ->withQuery($nextLink));
        }
        if ($page > 1) {
            $prevLink = http_build_query(array_merge($query, [
                'page' => $page - 1
            ]));
            $hal->addLink('prev', (string) $this->request->getUri()
                ->withQuery($prevLink));
        }

        if ($paginator->count() > 1) {
            $lastLink = http_build_query(array_merge($query, [
                'page' => $paginator->count()
            ]));
            $hal->addLink('last', (string) $this->request->getUri()
                ->withQuery($lastLink));
        }

        return json_decode($hal->asJson(true), true);
    }

    private function fetch($id)
    {
        try {
            $entity = $this->provider->fetch($id);
        } catch (\Exception $ex) {
            throw $ex;
        }

        if (!$entity) {
            throw new \Exception("Entity not found.", 404);
        }

        return $this->createHalEntity($entity);
    }

    private function createHalEntity($entity)
    {
        if ($entity instanceof \ArrayObject) {
            $entity = (array) $entity;
        } elseif (method_exists($entity, 'getArrayCopy')) {
            $entity = $entity->getArrayCopy();
        }

        $hal = new Hal((string) $this->request->getUri()->withQuery(''), $entity);

        return json_decode($hal->asJson(false, true), true, 10);
    }

}
