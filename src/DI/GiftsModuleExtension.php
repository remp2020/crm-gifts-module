<?php

namespace Crm\GiftsModule\DI;

use Kdyby\Translation\DI\ITranslationProvider;
use Nette\DI\CompilerExtension;

class GiftsModuleExtension extends CompilerExtension implements ITranslationProvider
{
    private $defaults = [];

    public function loadConfiguration()
    {
        // set default values if user didn't define them
        $this->config = $this->validateConfig($this->defaults);

        // load services from config and register them to Nette\DI Container
        $this->compiler->loadDefinitionsFromConfig(
            $this->loadFromFile(__DIR__.'/../config/config.neon')['services']
        );
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        // load presenters from extension to Nette
        $builder->getDefinition($builder->getByType(\Nette\Application\IPresenterFactory::class))
            ->addSetup('setMapping', [['Gifts' => 'Crm\GiftsModule\Presenters\*Presenter']]);
    }

    /**
     * Return array of directories, that contain resources for translator.
     * @return string[]
     */
    public function getTranslationResources()
    {
        return [__DIR__ . '/../lang/'];
    }
}
