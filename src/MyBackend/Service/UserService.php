<?php
/**
 * @author Stefano Torresi (http://stefanotorresi.it)
 * @license See the file LICENSE.txt for copying permission.
 * ************************************************
 */

namespace MyBackend\Service;

use MyBackend\Entity\User;
use Zend\Permissions\Rbac\RoleInterface;
use ZfcUser\Service\User as ZfcUserService;

class UserService extends ZfcUserService
{
    protected $roleMapper;

    public function addRoleToUser($role, User $user, $update = true)
    {
        if ($role instanceof RoleInterface) {
            $role = $role->getName();
        } else {
            $role = (string) $role;
        }

        $roleEntity = $this->getRoleMapper()->findOneByName($role);

        if (! $roleEntity) {
            throw new Exception\UserServiceException("'$role' role not found.");
        }

        $user->addRole($roleEntity);

        if ($update) {
            $this->getUserMapper()->update($user);
        }
    }

    public function addRolesToUser($roles, User $user, $update = true)
    {
        foreach ($roles as $role) {
            $this->addRoleToUser($role, $user, false);
        }

        if ($update) {
            $this->getUserMapper()->update($user);
        }
    }

    /**
     * @return mixed
     */
    public function getRoleMapper()
    {
        if (null === $this->roleMapper) {
            $this->roleMapper = $this->getServiceManager()->get('MyBackend\Mapper\RoleMapper');
        }

        return $this->roleMapper;
    }

    /**
     * @param mixed $roleMapper
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }
}
