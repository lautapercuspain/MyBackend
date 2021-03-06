<?php
/**
 * @author Stefano Torresi (http://stefanotorresi.it)
 * @license See the file LICENSE.txt for copying permission.
 * ************************************************
 */

namespace MyBackend\Controller;

use MyBackend\Mapper\DeleteCapableUserMapper;
use MyBackend\Service\Exception\UserServiceException;
use MyBase\Controller\AbstractConsoleController;
use MyBackend\Entity;
use MyBackend\Service\UserService;
use Zend\Console\Adapter\Posix;
use Zend\Console\ColorInterface as Color;
use Zend\Console\Console;
use Zend\Console\Prompt;
use Zend\Permissions\Rbac\Rbac;

class UserConsoleController extends AbstractConsoleController
{
    /**
     * @var UserService $userService
     */
    protected $userService;

    /**
     * @var Rbac $rbac
     */
    protected $rbac;

    /**
     *
     */
    public function createAction()
    {
        $username       = $this->params('username') ?: Prompt\Line::prompt('Please enter a username: ', false, 255);
        $email          = $this->params('email') ?: Prompt\Line::prompt('Please enter an email: ', false, 255);
        $displayName    = $this->getUserService()->getOptions()->getEnableDisplayName() ?
            Prompt\Line::prompt('Please enter a display name: ', false, 50) : null;

        $console = $this->getConsole();

        if ($console instanceof Posix) {
            shell_exec('stty -echo');
        }

        $password       = Prompt\Line::prompt('Please enter a password: ');
        $console->writeLine();
        $passwordVerify = Prompt\Line::prompt('Please confirm the password: ');

        if ($console instanceof Posix) {
            shell_exec('stty echo');
        }

        $console->writeLine();

        $roles = $this->params('roles') ?: Prompt\Line::prompt('Please enter a comma separated list of user roles: [guest] ', true, 32);
        if (empty($roles)) {
            $roles = 'guest';
        }

        $roles = explode(',', $roles);

        /** @var Entity\User $user */
        $user = $this->getUserService()->register([
            'username'          => $username,
            'email'             => $email,
            'display_name'      => $displayName,
            'password'          => $password,
            'passwordVerify'    => $passwordVerify,
        ]);

        if (! $user) {

            $console->writeLine(PHP_EOL.'Invalid data provided', Color::RED);

            $form = $this->getUserService()->getRegisterForm();

            foreach ($form->getMessages() as $field => $messages) {
                foreach ($messages as $message) {
                    $console->writeLine($form->get($field)->getLabel(). ': '.$message);
                }
            }

            return;
        }

        $userMapper = $this->getUserService()->getUserMapper();

        try {
            $this->getUserService()->addRolesToUser($roles, $user);
        } catch (\Exception $e) {
            if ($userMapper instanceof DeleteCapableUserMapper) {
                $userMapper->delete($user); // rollback if we can't update user with roles
            }

            if ($e instanceof UserServiceException) {
                $console->writeLine();
                $console->writeLine("Error: ".$e->getMessage(), Color::RED);

                return;
            }
            throw $e;
        }

        $console->writeLine();
        $console->writeLine(sprintf('User \'%s\' added', $username), Color::GREEN);
    }

    public function deleteAction()
    {
        $search     = [];
        $searchKeys = ['username', 'email'];
        $console    = Console::getInstance();

        foreach ($searchKeys as $key) {
            $param = $this->params($key);
            if ($param) {
                $search[$key] = $param;
            }
        }

        if (empty($search)) {
            $search['unknown'] = Prompt\Line::prompt('Please enter username or email: ');
        }

        $user = null;

        foreach ($searchKeys as $searchKey) {
            $searchMultipleKeys = true;
            $searchMethod       = 'findBy'.$searchKey;
            $searchValue        = null;

            if (array_key_exists($searchKey, $search)) {
                $searchValue = $search[$searchKey];
                $searchMultipleKeys = false;
            } elseif ( isset($search['unknown']) ) {
                $searchValue = $search['unknown'];
            }

            if (! $searchValue) {
                continue;
            }

            $user = $this->getUserService()->getUserMapper()->$searchMethod($searchValue);

            if (! $searchMultipleKeys || $user) {
                break;
            }
        }

        if (! $user instanceof Entity\User) {
            $console->writeLine(PHP_EOL.'User not found', Color::RED);

            return;
        }

        $console->writeLine(
            "User found".PHP_EOL
            ." Id: \t\t"            .$user->getId().PHP_EOL
            ." Username: \t"        .$user->getUsername().PHP_EOL
            ." Email: \t"           .$user->getEmail().PHP_EOL
            ." Display name: \t"    .$user->getDisplayName().PHP_EOL
            ." State: \t"           .$user->getState().PHP_EOL
            ." Roles: \t"           .implode(', ',$user->getRoles())
        );

        $confirm = Prompt\Confirm::prompt($console->colorize(
            PHP_EOL.'Are you sure you want to delete this user? [y/n]',
            Color::YELLOW
        ));

        if (! $confirm) {
            $console->writeLine(PHP_EOL.'Aborted', Color::LIGHT_RED);

            return;
        }

        $this->getUserService()->getUserMapper()->delete($user);

        $console->writeLine(
            PHP_EOL.sprintf("User '%s' deleted", $user->getUsername()),
            Color::GREEN
        );
    }

    /**
     * @return UserService
     */
    public function getUserService()
    {
        if (! $this->userService) {
            $this->userService = $this->getServiceLocator()->get('zfcuser_user_service');
        }

        return $this->userService;
    }

    /**
     * @param UserService $userService
     */
    public function setUserService($userService)
    {
        $this->userService = $userService;
    }

    /**
     * @return Rbac
     */
    public function getRbac()
    {
        if (! $this->rbac) {
            $this->rbac = $this->getServiceLocator()->get('ZfcRbac\Service\AuthorizationService')->getRbac();
        }

        return $this->rbac;
    }

    /**
     * @param Rbac $rbac
     */
    public function setRbac($rbac)
    {
        $this->rbac = $rbac;
    }
}
