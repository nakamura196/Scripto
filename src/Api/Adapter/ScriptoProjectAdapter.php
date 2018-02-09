<?php
namespace Scripto\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ScriptoProjectAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'scripto_projects';
    }

    public function getRepresentationClass()
    {
        return 'Scripto\Api\Representation\ScriptoProjectRepresentation';
    }

    public function getEntityClass()
    {
        return 'Scripto\Entity\ScriptoProject';
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['owner_id'])) {
            $alias = $this->createAlias();
            $qb->innerJoin('Scripto\Entity\ScriptoProject.owner', $alias);
            $qb->andWhere($qb->expr()->eq(
                "$alias.id",
                $this->createNamedParameter($qb, $query['owner_id']))
            );
        }
        if (isset($query['item_set_id'])) {
            $alias = $this->createAlias();
            $qb->innerJoin('Scripto\Entity\ScriptoProject.itemSet', $alias);
            $qb->andWhere($qb->expr()->eq(
                "$alias.id",
                $this->createNamedParameter($qb, $query['item_set_id']))
            );
        }
        if (isset($query['property_id'])) {
            $alias = $this->createAlias();
            $qb->innerJoin('Scripto\Entity\ScriptoProject.property', $alias);
            $qb->andWhere($qb->expr()->eq(
                "$alias.id",
                $this->createNamedParameter($qb, $query['property_id']))
            );
        }
    }
    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (isset($data['o:owner']) && !isset($data['o:owner']['o:id'])) {
            $errorStore->addError('o:owner', 'An owner must have an ID'); // @translate
        }
        if (isset($data['o:item_set']) && !isset($data['o:item_set']['o:id'])) {
            $errorStore->addError('o:item_set', 'An item set must have an ID'); // @translate
        }
        if (isset($data['o:property']) && !isset($data['o:property']['o:id'])) {
            $errorStore->addError('o:property', 'A property must have an ID'); // @translate
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore)
    {
        $this->hydrateOwner($request, $entity);
        if ($this->shouldHydrate($request, 'o:item_set')) {
            $itemSet = $request->getValue('o:item_set');
            if ($itemSet) {
                $itemSet = $this->getAdapter('item_sets')->findEntity($itemSet['o:id']);
            }
            $entity->setItemSet($itemSet);
        }
        if ($this->shouldHydrate($request, 'o:property')) {
            $property = $request->getValue('o:property');
            if ($property) {
                $property = $this->getAdapter('properties')->findEntity($property['o:id']);
            }
            $entity->setProperty($property);
        }
        if ($this->shouldHydrate($request, 'o-module-scripto:lang')) {
            $entity->setLang($request->getValue('o-module-scripto:lang'));
        }
        if ($this->shouldHydrate($request, 'o-module-scripto:title')) {
            $entity->setTitle($request->getValue('o-module-scripto:title'));
        }
        if ($this->shouldHydrate($request, 'o-module-scripto:description')) {
            $entity->setDescription($request->getValue('o-module-scripto:description'));
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        if (null === $entity->getTitle()) {
            $errorStore->addError('o-module-scripto:title', 'A Scripto project title must not be null'); // @translate
        }
    }
}
