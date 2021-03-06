<?php
namespace Topxia\Service\Common;

use Monolog\Logger;
use Topxia\Service\Common\Lock;
use Monolog\Handler\StreamHandler;
use Topxia\Service\Common\ServiceException;
use Topxia\Service\Common\NotFoundException;
use Topxia\Service\Util\HTMLPurifier;
use Topxia\Service\Common\AccessDeniedException;

abstract class BaseService
{
    private $logger = null;
    private $lock   = null;

    protected function createService($name)
    {
        return $this->getKernel()->createService($name);
    }

    protected function createDao($name)
    {
        return $this->getKernel()->createDao($name);
    }

    protected function getKernel()
    {
        return ServiceKernel::instance();
    }

    public function getCurrentUser()
    {
        return $this->getKernel()->getCurrentUser();
    }

    public function getEnvVariable($key = null)
    {
        return $this->getKernel()->getEnvVariable($key);
    }

    public function getDispatcher()
    {
        return ServiceKernel::dispatcher();
    }

    protected function dispatchEvent($eventName, $subject)
    {
        if ($subject instanceof ServiceEvent) {
            $event = $subject;
        } else {
            $event = new ServiceEvent($subject);
        }

        return $this->getDispatcher()->dispatch($eventName, $event);
    }

    protected function purifyHtml($html, $trusted = false)
    {
        if (empty($html)) {
            return '';
        }

        $config = array(
            'cacheDir' => $this->getKernel()->getParameter('kernel.cache_dir').'/htmlpurifier',
            'safeIframeDomains' => $this->setting('security.safe_iframe_domains', array()),
        );

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html, $trusted);
    }

    /**
     * @deprecated this is deprecated and will be removed. Please use use `throw new Topxia\Common\Exception\XXXException(...)` instead.
     */
    protected function createServiceException($message = 'Service Exception', $code = 0)
    {
        throw new ServiceException($message, $code);
    }

    /**
     * @deprecated this is deprecated and will be removed. Please use use `throw new Topxia\Common\Exception\XXXException(...)` instead.
     */
    protected function createAccessDeniedException($message = 'Access Denied', $code = 0)
    {
        throw new AccessDeniedException($message, null, $code);
    }

    /**
     * @deprecated this is deprecated and will be removed. Please use use `throw new Topxia\Common\Exception\XXXException(...)` instead.
     */
    protected function createNotFoundException($message = 'Not Found', $code = 0)
    {
        throw new NotFoundException($message, $code);
    }

    protected function fillOrgId($fields)
    {
        $magic = $this->createService('System.SettingService')->get('magic');

        if (isset($magic['enable_org']) && $magic['enable_org']) {
            if (!empty($fields['orgCode'])) {
                $org = $this->createService('Org:Org.OrgService')->getOrgByOrgCode($fields['orgCode']);
                if (empty($org)) {
                    throw $this->createServiceException($this->getKernel()->trans('????????????%orgCode%?????????,????????????', array('%orgCode%' => $fields['orgCode'])));
                }
                $fields['orgId']   = $org['id'];
                $fields['orgCode'] = $org['orgCode'];
            } else {
                unset($fields['orgCode']);
            }
        } else {
            unset($fields['orgCode']);
        }
        return $fields;
    }

    protected function trans($text)
    {
        return $this->getKernel()->trans($text);
    }

    protected function getLogger($name)
    {
        if ($this->logger) {
            return $this->logger;
        }

        $this->logger = new Logger($name);
        $this->logger->pushHandler(new StreamHandler(ServiceKernel::instance()->getParameter('kernel.logs_dir').'/service.log', Logger::DEBUG));

        return $this->logger;
    }

    protected function isAdminUser()
    {
        $user = $this->getCurrentUser();

        if (empty($user->id)) {
            throw $this->createAccessDeniedException('?????????????????????????????????');
        }

        $permissions = $user->getPermissions();
        if (in_array('admin', array_keys($permissions))) {
            return true;
        }
        return false;
    }

    public function isPluginInstalled($code)
    {
        $appService = $this->createService('CloudPlatform.AppService');
        $plugin = $appService->getAppByCode($code);
        if(empty($plugin)) {
            return false;
        }

        return true;
    }

    public function setting($name, $default = '')
    {
        $names = explode('.', $name);
        $setting = $this->createService('System.SettingService')->get($names[0]);
        if(empty($names[1])) {
            return empty($setting) ? $default : $setting;
        } 

        return empty($setting[$names[1]]) ? $default : $setting[$names[1]];

    }

    protected function getLock()
    {
        if (!$this->lock) {
            $this->lock = new Lock();
        }

        return $this->lock;
    }
}
