<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM;

use Doctrine\Common\Util\Inflector;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Collections\Criteria;

/**
 * An EntityRepository serves as a repository for entities with generic as well as
 * business specific methods for retrieving entities.
 *
 * This class is designed for inheritance and users can subclass this class to
 * write their own repositories with business-specific methods to locate entities.
 *
 * @since   2.0
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class EntityRepository implements ObjectRepository, Selectable
{
    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var \Doctrine\ORM\Mapping\ClassMetadata
     */
    protected $class;

    /**
     * Initializes a new <tt>EntityRepository</tt>.
     *
     * @param EntityManager         $em    The EntityManager to use.
     * @param Mapping\ClassMetadata $class The class descriptor.
     */
    public function __construct(EntityManagerInterface $em, Mapping\ClassMetadata $class)
    {
        $this->entityName = $class->getClassName();
        $this->em         = $em;
        $this->class      = $class;
    }

    /**
     * Creates a new QueryBuilder instance that is prepopulated for this entity name.
     *
     * @param string $alias
     * @param string $indexBy The index for the from.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder($alias, $indexBy = null)
    {
        return $this->em->createQueryBuilder()
            ->select($alias)
            ->from($this->entityName, $alias, $indexBy);
    }

    /**
     * Creates a new result set mapping builder for this entity.
     *
     * The column naming strategy is "INCREMENT".
     *
     * @param string $alias
     *
     * @return ResultSetMappingBuilder
     */
    public function createResultSetMappingBuilder($alias)
    {
        $rsm = new ResultSetMappingBuilder($this->em, ResultSetMappingBuilder::COLUMN_RENAMING_INCREMENT);
        $rsm->addRootEntityFromClassMetadata($this->entityName, $alias);

        return $rsm;
    }

    /**
     * Creates a new Query instance based on a predefined metadata named query.
     *
     * @param string $queryName
     *
     * @return Query
     */
    public function createNamedQuery($queryName)
    {
        return $this->em->createQuery($this->class->getNamedQuery($queryName));
    }

    /**
     * Creates a native SQL query.
     *
     * @param string $queryName
     *
     * @return NativeQuery
     */
    public function createNativeNamedQuery($queryName)
    {
        $queryMapping   = $this->class->getNamedNativeQuery($queryName);
        $rsm            = new Query\ResultSetMappingBuilder($this->em);
        $rsm->addNamedNativeQueryMapping($this->class, $queryMapping);

        return $this->em->createNativeQuery($queryMapping['query'], $rsm);
    }

    /**
     * Clears the repository, causing all managed entities to become detached.
     *
     * @return void
     */
    public function clear()
    {
        $this->em->clear($this->class->getRootClassName());
    }

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed    $id          The identifier.
     * @param int|null $lockMode    One of the \Doctrine\DBAL\LockMode::* constants
     *                              or NULL if no specific lock mode should be used
     *                              during the search.
     * @param int|null $lockVersion The lock version.
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function find($id, $lockMode = null, $lockVersion = null)
    {
        return $this->em->find($this->entityName, $id, $lockMode, $lockVersion);
    }

    /**
     * Finds all entities in the repository.
     *
     * @return array The entities.
     */
    public function findAll()
    {
        return $this->findBy([]);
    }

    /**
     * Finds entities by a set of criteria.
     *
     * @param array    $criteria
     * @param array    $orderBy
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return array The objects.
     *
     * @todo guilhermeblanco Change orderBy to use a blank array by default (requires Common\Persistence change).
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $persister = $this->em->getUnitOfWork()->getEntityPersister($this->entityName);

        return $persister->loadAll($criteria, $orderBy !== null ? $orderBy : [], $limit, $offset);
    }

    /**
     * Finds a single entity by a set of criteria.
     *
     * @param array $criteria
     * @param array $orderBy
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function findOneBy(array $criteria, array $orderBy = [])
    {
        $persister = $this->em->getUnitOfWork()->getEntityPersister($this->entityName);

        return $persister->load($criteria, null, null, [], null, 1, $orderBy);
    }

    /**
     * Counts entities by a set of criteria.
     *
     * @todo Add this method to `ObjectRepository` interface in the next major release
     *
     * @param array $criteria
     *
     * @return int The cardinality of the objects that match the given criteria.
     */
    public function count(array $criteria)
    {
        return $this->em->getUnitOfWork()->getEntityPersister($this->entityName)->count($criteria);
    }

    /**
     * Adds support for magic method calls.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed The returned value from the resolved method.
     *
     * @throws ORMException
     * @throws \BadMethodCallException If the method called is invalid
     */
    public function __call($method, $arguments)
    {
        if (0 === strpos($method, 'findBy')) {
            return $this->resolveMagicCall('findBy', substr($method, 6), $arguments);
        }

        if (0 === strpos($method, 'findOneBy')) {
            return $this->resolveMagicCall('findOneBy', substr($method, 9), $arguments);
        }

        if (0 === strpos($method, 'countBy')) {
            return $this->resolveMagicCall('count', substr($method, 7), $arguments);
        }

        throw new \BadMethodCallException(
            "Undefined method '$method'. The method name must start with ".
            "either findBy, findOneBy or countBy!"
        );
    }

    /**
     * @return string
     */
    protected function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->getEntityName();
    }

    /**
     * @return EntityManager
     */
    protected function getEntityManager()
    {
        return $this->em;
    }

    /**
     * @return Mapping\ClassMetadata
     */
    protected function getClassMetadata()
    {
        return $this->class;
    }

    /**
     * Select all elements from a selectable that match the expression and
     * return a new collection containing these elements.
     *
     * @param \Doctrine\Common\Collections\Criteria $criteria
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function matching(Criteria $criteria)
    {
        $persister = $this->em->getUnitOfWork()->getEntityPersister($this->entityName);

        return new LazyCriteriaCollection($persister, $criteria);
    }

    /**
     * Resolves a magic method call to the proper existent method at `EntityRepository`.
     *
     * @param string $method    The method to call
     * @param string $by        The property name used as condition
     * @param array  $arguments The arguments to pass at method call
     *
     * @throws ORMException If the method called is invalid or the requested field/association does not exist
     *
     * @return mixed
     */
    private function resolveMagicCall($method, $by, array $arguments)
    {
        if (! $arguments) {
            throw ORMException::findByRequiresParameter($method . $by);
        }

        $fieldName = lcfirst(Inflector::classify($by));

        if (null === $this->class->getProperty($fieldName)) {
            throw ORMException::invalidMagicCall($this->entityName, $fieldName, $method . $by);
        }

        return $this->$method([$fieldName => $arguments[0]], ...array_slice($arguments, 1));
    }
}
