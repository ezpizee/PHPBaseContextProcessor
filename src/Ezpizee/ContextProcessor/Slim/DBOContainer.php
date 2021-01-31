<?php

namespace Ezpizee\ContextProcessor\Slim;

use Ezpizee\ContextProcessor\DBO;
use Ezpizee\ContextProcessor\DBCredentials;
use Pimple\Container;
use RuntimeException;
use Slim\Collection;

class DBOContainer
{
    /**
     * @var DBO
     */
    private $dbo;

    public function __invoke(Container $container): DBO {
        if (empty($this->dbo)) {
            $settings = $container->get('settings');
            if ($settings instanceof Collection) {
                $ezpizee = $settings->get('ezpizee');
                if (!empty($ezpizee) || !isset($ezpizee['dbo']) || !isset($ezpizee['dbo']['connection'])) {
                    $this->dbo = self::getDBO($ezpizee['dbo']['connection']);
                }
                else {
                    throw new RuntimeException('ezpizee setting is missing', 500);
                }
            }
        }
        return $this->dbo;
    }

    public static function getDBO(array $config): DBO {
        return new DBO(new DBCredentials($config));
    }
}