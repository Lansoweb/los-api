<?php
namespace LosApi\Action;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\Hydrator\ArraySerializable;
use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;

class ApiActionFactory implements AbstractFactoryInterface
{

    /**
     *
     * {@inheritDoc}
     *
     * @see \Zend\ServiceManager\AbstractFactoryInterface::canCreateServiceWithName()
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $config = $serviceLocator->get('config');

        if (!array_key_exists('los_api', $config)) {
            return false;
        }

        $apiConfig = $config['los_api'];
        $actions = isset($apiConfig['actions']) ? $apiConfig['actions'] : [];

        $keys = array_keys($actions);
        foreach ($keys as $action) {
            if ($action == $requestedName) {
                return true;
            }
        }

        return false;
    }

    /**
     *
     * {@inheritDoc}
     *
     * @see \Zend\ServiceManager\AbstractFactoryInterface::createServiceWithName()
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $config = $serviceLocator->get('config');

        $apiConfig = $config['los_api'];

        $actionOptions = array_merge([
            'provider' => null,
            'table_name' => null,
            'route_identifier_name' => 'id',
            'collection_name' => 'data',
            'entity_http_methods' => [
                'GET',
                'PUT',
                'PATCH',
                'DELETE',
            ],
            'collection_http_methods' => [
                'GET',
                'POST',
            ],
            'collection_query_whitelist' => [],
            'page_size' => 25,
            'page_size_param' => 'page',
            'entity_class' => null,
            'entity_identifier' => 'id',
            'collection_class' => null,
        ], $apiConfig['actions'][$requestedName]);

        if (empty($actionOptions['table_name'])) {
            throw new \Exception("Missing 'table_name' for $requestedName.");
        }
        $tableName = $actionOptions['table_name'];

        $entityOptions = [];
        if (!empty($actionOptions['entity_class']) && array_key_exists('entities', $apiConfig)
            && array_key_exists($actionOptions['entity_class'], $apiConfig['entities'])) {
            $entityOptions = $apiConfig['entities'][$actionOptions['entity_class']];
        }

        $entityPrototype = null;
        if (!empty($actionOptions['entity_class'])) {
            $entityPrototype = new $actionOptions['entity_class'];
        }

        $hydrator = new ArraySerializable();
        $resultSet = new \Zend\Db\ResultSet\HydratingResultSet($hydrator, $entityPrototype);

        $adapter = new Adapter($config['db']);
        $table = new TableGateway($tableName, $adapter, null, $resultSet);

        if (!empty($actionOptions['provider'])) {
            $provider = new $actionOptions['provider'];
        } else {
            $provider = new GenericProvider($table);
        }

        $action = new ApiAction($table, $provider, $actionOptions, $entityOptions);

        return $action;
    }
}