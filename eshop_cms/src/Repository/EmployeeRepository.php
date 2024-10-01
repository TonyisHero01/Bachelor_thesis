<?php

namespace App\Repository;

use App\Entity\Employee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Doctrine\ORM\Query\ResultSetMapping;

/**
 * @extends ServiceEntityRepository<Employee>
 * @implements PasswordUpgraderInterface<Employee>
 *
 * @method Employee|null find($id, $lockMode = null, $lockVersion = null)
 * @method Employee|null findOneBy(array $criteria, array $orderBy = null)
 * @method Employee[]    findAll()
 * @method Employee[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmployeeRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Employee) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    // 在 EmployeeRepository 中修改 findAllEmployees() 方法：
    public function findAllEmployees()
    {
        return $this->createQueryBuilder('e')
                    ->where('e.id != :id')
                    ->setParameter('id', 1)
                    ->getQuery()
                    ->getResult();
    }
    public function deleteEmployee(Employee $employee): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($employee);
        $entityManager->flush();
    }
    public function findAllWithRoleAdmin(): array
    {
        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(Employee::class, 'e');
        $rsm->addFieldResult('e', 'id', 'id');
        $rsm->addFieldResult('e', 'username', 'username');
        $rsm->addFieldResult('e', 'surname', 'surname');
        $rsm->addFieldResult('e', 'name', 'name');
        $rsm->addFieldResult('e', 'email', 'email');
        $rsm->addFieldResult('e', 'phone_number', 'phoneNumber');
        $rsm->addFieldResult('e', 'roles', 'roles');
        
        $sql = 'SELECT * FROM employee e WHERE e.roles @> :role';
        
        $entityManager = $this->getEntityManager();
    
        // 创建 Native Query
        $query = $entityManager->createNativeQuery($sql, $rsm);
        $query->setParameter('role', json_encode(['ROLE_ADMIN']));

        return $query->getResult();

    }
//    /**
//     * @return Employee[] Returns an array of Employee objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Employee
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
