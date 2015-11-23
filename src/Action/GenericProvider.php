<?php
namespace LosApi\Action;

use Zend\Db\TableGateway\TableGatewayInterface;

class GenericProvider implements ProviderInterface
{

    protected $table;

    protected $entityIdentifier;

    /**
     *
     * {@inheritDoc}
     *
     * @see \LosApi\Action\ProviderInterface::__construct()
     */
    public function __construct(TableGatewayInterface $table)
    {
        $this->table = $table;
        $this->entityIdentifier = 'id';
    }

    public function setEntityIdentifier($id)
    {
        $this->entityIdentifier = $id;
    }

    public function fetch($id)
    {
        $entity = $this->table->select([
            $this->entityIdentifier => $id
        ]);

        if ($entity->count() > 0) {
            return $entity->current();
        }

        return null;
    }
}