<?php
namespace LosApi\Action;

use Zend\Db\TableGateway\TableGatewayInterface;

interface ProviderInterface
{

    public function __construct(TableGatewayInterface $table);
}
